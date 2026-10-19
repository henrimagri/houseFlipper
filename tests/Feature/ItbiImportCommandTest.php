<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Services\RealEstateData\ItbiDatasetDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItbiImportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('real_estate_data.itbi_sp.allowed_download_hosts', ['audit.test']);
    }

    private function localBatch(string $status, bool $twoRows = false): ItbiImportBatch
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Storage::disk('local')->put('recover.csv', "SQL;Data da Transacao;Valor da Transacao\n123;15/05/2025;100000".($twoRows ? "\n456;15/05/2025;200000" : ''));

        return ItbiImportBatch::create(['provider' => 'audit', 'source_url' => 'upload://recover.csv', 'source_filename' => 'recover.csv', 'storage_path' => 'recover.csv', 'status' => $status]);
    }

    public function test_failed_manual_batch_recovers_from_existing_file_without_network(): void
    {
        $batch = $this->localBatch('failed');
        $this->artisan('itbi:import', ['--batch' => $batch->id])->assertExitCode(0);
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 1);
        Http::assertNothingSent();
    }

    public function test_download_failure_produces_failure_exit_code(): void
    {
        Storage::fake('local');
        Http::fake(['audit.test/*' => Http::response('', 503)]);
        $batch = ItbiImportBatch::create(['provider' => 'audit', 'source_url' => 'https://audit.test/recover.csv', 'source_filename' => 'recover.csv', 'status' => 'discovered']);
        $this->artisan('itbi:import', ['--batch' => $batch->id])->assertExitCode(1);
        $this->assertSame('failed', $batch->refresh()->status);
    }

    public function test_partial_batch_can_be_retried_and_row_failure_is_not_success_exit(): void
    {
        $batch = $this->localBatch('completed_with_errors', true);
        $fail = true;
        RealEstateTransaction::creating(function ($transaction) use (&$fail) {
            if ($fail && $transaction->property_sql === '456') {
                throw new \RuntimeException('Simulated row failure');
            }
        });
        $this->artisan('itbi:import', ['--batch' => $batch->id])->assertExitCode(1);
        $this->assertSame('completed_with_errors', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 1);
        $fail = false;
        $this->artisan('itbi:import', ['--batch' => $batch->id])->assertExitCode(0);
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 2);
        Http::assertNothingSent();
    }

    public function test_invalid_cli_identifiers_and_limits_fail_before_importing(): void
    {
        $batch = $this->localBatch('downloaded');
        foreach (['0', '-1', 'invalid'] as $limit) {
            $this->artisan('itbi:import', ['--batch' => $batch->id, '--limit' => $limit])->assertExitCode(1);
        }
        $this->artisan('itbi:import', ['--batch' => '-1'])->assertExitCode(1);
        $this->artisan('itbi:import', ['--recover-stale' => '0'])->assertExitCode(1);
        $this->mock(ItbiDatasetDiscoveryService::class)
            ->shouldReceive('discover')->once()->andReturn(['found' => 0, 'created' => 0, 'existing' => 0]);
        $this->artisan('itbi:sync', ['--limit' => '0'])->assertExitCode(1);
        $this->assertDatabaseCount('real_estate_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_stale_processing_batch_requires_explicit_recovery_window(): void
    {
        $batch = $this->localBatch('processing');
        $batch->update(['processing_started_at' => now()->subMinutes(30)]);

        $this->artisan('itbi:import', ['--batch' => $batch->id])->assertExitCode(0);
        $this->assertSame('processing', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 0);

        $this->artisan('itbi:import', ['--batch' => $batch->id, '--recover-stale' => 60])->assertExitCode(0);
        $this->assertSame('processing', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 0);

        $this->artisan('itbi:import', ['--batch' => $batch->id, '--recover-stale' => 15])->assertExitCode(0);
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertDatabaseCount('real_estate_transactions', 1);
        Http::assertNothingSent();
    }

    public function test_sync_propagates_import_failure_exit_code(): void
    {
        Storage::fake('local');
        Http::fake(['audit.test/*' => Http::response('', 503)]);
        $this->mock(ItbiDatasetDiscoveryService::class)
            ->shouldReceive('discover')->once()->andReturn(['found' => 0, 'created' => 0, 'existing' => 0]);
        ItbiImportBatch::create(['provider' => 'audit', 'source_url' => 'https://audit.test/recover.csv', 'source_filename' => 'recover.csv', 'status' => 'discovered']);
        $this->artisan('itbi:sync')->assertExitCode(1);
    }
}
