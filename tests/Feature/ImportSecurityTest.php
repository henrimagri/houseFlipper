<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Models\User;
use App\Services\RealEstateData\ItbiTransactionImportService;
use App\Services\RealEstateData\ItbiTransactionNormalizerService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImportSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('itbi.import', 'web'));
        $this->actingAs($user, 'sanctum');
    }

    public function test_internal_php_upload_limits_are_not_exposed_as_an_api(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/itbi-imports/upload-limits')
            ->assertNotFound()
            ->assertJsonMissingPath('memory_limit')
            ->assertJsonMissingPath('upload_max_filesize');
    }

    public function test_original_import_filename_is_visible_only_to_import_operators(): void
    {
        ItbiImportBatch::create([
            'provider' => 'manual_upload_itbi_sp',
            'source_url' => 'upload://CLIENTE-JOAO-CPF-PRIVATE.csv',
            'source_filename' => 'CLIENTE-JOAO-CPF-PRIVATE.csv',
            'status' => 'completed',
        ]);

        $regular = User::factory()->create();
        $this->actingAs($regular, 'sanctum')->getJson('/api/itbi-imports')
            ->assertOk()
            ->assertJsonPath('can_import', false)
            ->assertJsonPath('summary.latest_batch.source_filename', null)
            ->assertJsonMissing(['CLIENTE-JOAO-CPF-PRIVATE.csv']);

        $operator = User::factory()->create();
        $operator->givePermissionTo(Permission::findOrCreate('itbi.import', 'web'));
        $this->actingAs($operator, 'sanctum')->getJson('/api/itbi-imports')
            ->assertOk()
            ->assertJsonPath('can_import', true)
            ->assertJsonPath('summary.latest_batch.source_filename', 'CLIENTE-JOAO-CPF-PRIVATE.csv');
    }

    public function test_impossible_dates_are_not_silently_shifted_into_another_month(): void
    {
        $normalizer = app(ItbiTransactionNormalizerService::class);
        $invalid = $normalizer->normalize(['data_transacao' => '31/02/2026', 'valor' => '100000'], 2, 'Audit');
        $this->assertNull($invalid['transaction_date']);
        $valid = $normalizer->normalize(['data_transacao' => '28/02/2026', 'valor' => '100000'], 2, 'Audit');
        $this->assertSame('2026-02-28', $valid['transaction_date']);
        $leap = $normalizer->normalize(['data_transacao' => '29/02/2024', 'valor' => '100000'], 2, 'Audit');
        $this->assertSame('2024-02-29', $leap['transaction_date']);
        $month = $normalizer->normalize(['data_transacao' => '02/2026', 'valor' => '100000'], 2, 'Audit');
        $this->assertSame('2026-02-01', $month['transaction_date']);
        $invalidLeap = $normalizer->normalize(['data_transacao' => '2026-02-29', 'valor' => '100000'], 2, 'Audit');
        $this->assertNull($invalidLeap['transaction_date']);
    }

    public function test_operator_can_import_valid_csv_and_repeat_without_duplicate_transactions(): void
    {
        $this->operator();
        $csv = "SQL;Data da Transacao;Valor da Transacao;Tipo do Imovel;Logradouro;Numero;Bairro;Area Util\n123;15/05/2025;735000;Apartamento;Rua Teste;100;Moema;98";
        for ($i = 0; $i < 2; $i++) {
            $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('audit.csv', $csv)], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->assertDatabaseCount('real_estate_transactions', 1);
    }

    public function test_operator_cannot_import_executable_or_empty_dataset(): void
    {
        $this->operator();
        $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('audit.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('empty.csv', 'irrelevant;headers')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('real_estate_transactions', 0);
    }

    public function test_upload_storage_failure_does_not_create_batch_or_start_import(): void
    {
        $this->operator();
        $results = [false, new \RuntimeException('PRIVATE_STORAGE_MARKER')];
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->twice()->andReturnFalse();
        $disk->shouldReceive('putFileAs')->twice()->andReturnUsing(function () use (&$results) {
            $result = array_shift($results);
            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result;
        });
        Storage::shouldReceive('disk')->twice()->andReturn($disk);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('storage.csv', "SQL;Valor\n123;100000")], ['Accept' => 'application/json'])
                ->assertStatus(503)->assertJsonPath('message', 'Não foi possível armazenar o arquivo. Tente novamente.');
        }
        $this->assertDatabaseCount('itbi_import_batches', 0);
        $this->assertDatabaseCount('real_estate_transactions', 0);
    }

    public function test_batch_database_failure_removes_newly_stored_upload(): void
    {
        $this->operator();
        Event::listen('eloquent.creating: '.ItbiImportBatch::class, fn () => throw new \RuntimeException('simulated batch failure'));

        try {
            $this->post('/api/itbi-imports/upload', [
                'file' => UploadedFile::fake()->createWithContent('orphan.csv', "SQL;Valor\n123;100000"),
            ], ['Accept' => 'application/json'])->assertStatus(500);

            $this->assertDatabaseCount('itbi_import_batches', 0);
            $this->assertDatabaseCount('real_estate_transactions', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            Event::forget('eloquent.creating: '.ItbiImportBatch::class);
        }
    }

    public function test_batch_database_failure_preserves_preexisting_content_addressed_upload(): void
    {
        $this->operator();
        $contents = "SQL;Valor\n123;100000";
        $path = trim(config('real_estate_data.itbi_sp.storage_path'), '/')
            .'/upload-existing-'.hash('sha256', $contents).'.csv';
        Storage::disk('local')->put($path, $contents);
        Event::listen('eloquent.creating: '.ItbiImportBatch::class, fn () => throw new \RuntimeException('simulated batch failure'));

        try {
            $this->post('/api/itbi-imports/upload', [
                'file' => UploadedFile::fake()->createWithContent('existing.csv', $contents),
            ], ['Accept' => 'application/json'])->assertStatus(500);

            Storage::disk('local')->assertExists($path);
            $this->assertSame($contents, Storage::disk('local')->get($path));
            $this->assertDatabaseCount('itbi_import_batches', 0);
        } finally {
            Event::forget('eloquent.creating: '.ItbiImportBatch::class);
        }
    }

    public function test_partial_import_reports_warning_and_reupload_recovers_without_duplicates(): void
    {
        $this->operator();
        $fail = true;
        RealEstateTransaction::creating(function ($transaction) use (&$fail) {
            if ($fail && $transaction->property_sql === '456') {
                throw new \RuntimeException('Simulated isolated row failure');
            }
        });
        $csv = "SQL;Data da Transacao;Valor da Transacao;Logradouro;Numero;Area Util\n123;15/05/2025;100000;Rua A;10;100\n456;15/05/2025;200000;Rua B;20;100";
        $response = $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('partial.csv', $csv)], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('batch.status', 'completed_with_errors')
            ->assertJsonPath('summary.imported_rows', 1)->assertJsonPath('summary.failed_rows', 1)
            ->assertJsonPath('message', 'Importação parcial: algumas linhas falharam. Confira os totais do lote antes de usar os dados.');
        $this->assertDatabaseCount('real_estate_transactions', 1);
        $fail = false;
        $this->post('/api/itbi-imports/upload', ['file' => UploadedFile::fake()->createWithContent('partial.csv', $csv)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('batch.status', 'completed')
            ->assertJsonPath('summary.imported_rows', 1)->assertJsonPath('summary.updated_rows', 1)->assertJsonPath('summary.failed_rows', 0);
        $this->assertDatabaseCount('real_estate_transactions', 2);
    }

    public function test_schema_oversized_row_is_rejected_consistently_before_database_write(): void
    {
        $this->operator();
        $csv = "SQL;Data da Transacao;Valor da Transacao;Logradouro;Numero;Area Util\n"
            ."AUDIT-VALID;15/05/2025;100000;Rua Valida;10;100\n"
            .'AUDIT-OVERSIZED;15/05/2025;200000;'.str_repeat('A', 181).';20;100';

        $this->post('/api/itbi-imports/upload', [
            'file' => UploadedFile::fake()->createWithContent('schema-bound.csv', $csv),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('batch.status', 'completed_with_errors')
            ->assertJsonPath('summary.imported_rows', 1)
            ->assertJsonPath('summary.failed_rows', 1);

        $this->assertDatabaseHas('real_estate_transactions', ['property_sql' => 'AUDIT-VALID']);
        $this->assertDatabaseMissing('real_estate_transactions', ['property_sql' => 'AUDIT-OVERSIZED']);
    }

    public function test_import_failures_do_not_copy_private_details_into_logs(): void
    {
        $this->operator();
        Log::spy();
        $importer = \Mockery::mock(ItbiTransactionImportService::class);
        $importer->shouldReceive('import')->once()->andThrow(new \RuntimeException('PRIVATE_IMPORT_MARKER'));
        $this->app->instance(ItbiTransactionImportService::class, $importer);

        $this->post('/api/itbi-imports/upload', [
            'file' => UploadedFile::fake()->createWithContent('PRIVATE_FILENAME.csv', "SQL;Valor\n123;100000"),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonMissing(['PRIVATE_IMPORT_MARKER', 'PRIVATE_FILENAME.csv']);

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode([$message, $context]);

            return $message === 'Falha ao importar arquivo ITBI enviado'
                && isset($context['filename_hash'], $context['exception_type'])
                && ! str_contains($encoded, 'PRIVATE_IMPORT_MARKER')
                && ! str_contains($encoded, 'PRIVATE_FILENAME.csv');
        });
    }
}
