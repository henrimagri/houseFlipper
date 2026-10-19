<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Services\RealEstateData\ItbiSpreadsheetParserService;
use App\Services\RealEstateData\ItbiTransactionImportService;
use App\Services\RealEstateData\ItbiTransactionNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ItbiImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_properties_without_cadastral_id_are_not_overwritten(): void
    {
        Storage::fake('local');
        $path = 'audit-no-cadastral-id.csv';
        Storage::disk('local')->put($path, "Data da Transacao;Valor da Transacao;Logradouro;Numero;Area Util\n15/05/2025;500000;Rua A;10;100\n15/05/2025;500000;Rua B;20;100");
        $batch = ItbiImportBatch::create(['provider' => 'audit', 'source_url' => 'upload://audit.csv', 'source_filename' => 'audit.csv', 'storage_path' => $path, 'status' => 'downloaded']);
        $importer = app(ItbiTransactionImportService::class);
        $importer->import($batch);
        $this->assertDatabaseCount('real_estate_transactions', 2);
        $migration = require database_path('migrations/2026_10_16_000002_rekey_itbi_records_without_cadastral_id.php');
        $migration->up();
        $importer->import($batch->refresh());
        $this->assertDatabaseCount('real_estate_transactions', 2);
        $this->assertSame(2, $batch->refresh()->updated_rows);
    }

    public function test_import_is_idempotent_for_csv_rows(): void
    {
        Storage::fake('local');
        config()->set('real_estate_data.itbi_sp.storage_disk', 'local');

        $path = 'real-estate-data/itbi/sp/raw/amostra.csv';
        Storage::disk('local')->put($path, implode("\n", [
            'SQL;Data da Transacao;Valor da Transacao;Tipo do Imovel;Logradouro;Numero;Bairro;Area Util;Cartorio',
            '123;15/05/2025;R$ 735.000,00;Apartamento;Rua Teste;100;Moema;98;1 RI',
        ]));

        $batch = ItbiImportBatch::create([
            'provider' => 'prefeitura_sp_itbi',
            'source_url' => 'https://example.test/amostra.csv',
            'source_filename' => 'amostra.csv',
            'storage_path' => $path,
            'status' => 'downloaded',
            'discovered_at' => now(),
            'downloaded_at' => now(),
        ]);

        app(ItbiTransactionImportService::class)->import($batch);
        $this->assertSame(1, RealEstateTransaction::count());
        $this->assertDatabaseHas('real_estate_transactions', [
            'property_sql' => '123',
            'price_m2' => 7500,
            'neighborhood' => 'Moema',
        ]);

        app(ItbiTransactionImportService::class)->import($batch->refresh());
        $this->assertSame(1, RealEstateTransaction::count());
        $this->assertSame(1, $batch->refresh()->updated_rows);
    }

    public function test_batch_already_being_processed_cannot_be_claimed_again(): void
    {
        Storage::fake('local');
        $path = 'processing.csv';
        Storage::disk('local')->put($path, "SQL;Valor da Transacao\n123;100000");
        $startedAt = now()->subMinute()->startOfSecond();
        $batch = ItbiImportBatch::create([
            'provider' => 'audit',
            'source_url' => 'upload://processing.csv',
            'source_filename' => 'processing.csv',
            'storage_path' => $path,
            'status' => 'processing',
            'processing_started_at' => $startedAt,
            'total_rows' => 7,
        ]);

        try {
            app(ItbiTransactionImportService::class)->import($batch);
            $this->fail('O lote já adquirido não poderia iniciar uma segunda execução.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Este lote já está sendo processado.', $exception->getMessage());
        }

        $batch->refresh();
        $this->assertSame('processing', $batch->status);
        $this->assertSame(7, $batch->total_rows);
        $this->assertTrue($startedAt->equalTo($batch->processing_started_at));
        $this->assertDatabaseCount('real_estate_transactions', 0);
    }

    public function test_unexpected_import_failure_does_not_expose_private_exception_details(): void
    {
        Storage::fake('local');
        Log::spy();
        $privateMarker = 'PRIVATE_DATABASE_HOST=db-internal.example.test';
        $parser = \Mockery::mock(ItbiSpreadsheetParserService::class);
        $parser->shouldReceive('rows')->once()->andReturnUsing(function () use ($privateMarker): \Generator {
            throw new \RuntimeException($privateMarker);
            yield [];
        });
        $batch = ItbiImportBatch::create([
            'provider' => 'audit',
            'source_url' => 'upload://private-error.csv',
            'source_filename' => 'private-error.csv',
            'storage_path' => 'private-error.csv',
            'status' => 'downloaded',
        ]);
        $importer = new ItbiTransactionImportService(
            $parser,
            app(ItbiTransactionNormalizerService::class),
        );

        try {
            $importer->import($batch);
            $this->fail('A falha inesperada deveria interromper a importação.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha ao processar o lote ITBI.', $exception->getMessage());
            $this->assertStringNotContainsString($privateMarker, $exception->getMessage());
            $this->assertSame($privateMarker, $exception->getPrevious()?->getMessage());
        }

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertSame('Falha ao processar o lote ITBI.', $batch->error_message);
        $this->assertStringNotContainsString($privateMarker, (string) $batch->error_message);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Falha ao processar lote ITBI'
                && $context['batch_id'] === $batch->id
                && $context['exception_type'] === \RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $privateMarker)
        );
    }

    public function test_import_reads_ods_itbi_file(): void
    {
        Storage::fake('local');
        config()->set('real_estate_data.itbi_sp.storage_disk', 'local');

        $path = 'real-estate-data/itbi/sp/raw/guias-itbi-pagas-2026.ods';
        $absolutePath = Storage::disk('local')->path($path);
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
    xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
    xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
    xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:spreadsheet>
      <table:table table:name="GUIAS DE ITBI PAGAS">
        <table:table-row>
          <table:table-cell><text:p>Nº do Cadastro</text:p></table:table-cell>
          <table:table-cell><text:p>Nome do Logradouro</text:p></table:table-cell>
          <table:table-cell><text:p>Número</text:p></table:table-cell>
          <table:table-cell><text:p>Bairro</text:p></table:table-cell>
          <table:table-cell><text:p>Natureza de Transação</text:p></table:table-cell>
          <table:table-cell><text:p>Data de Transação</text:p></table:table-cell>
          <table:table-cell><text:p>Valor da Transação</text:p></table:table-cell>
          <table:table-cell><text:p>Base de Cálculo adotada</text:p></table:table-cell>
          <table:table-cell><text:p>Cartório de Registro</text:p></table:table-cell>
          <table:table-cell><text:p>Área Construída</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>001.234.0001-9</text:p></table:table-cell>
          <table:table-cell><text:p>RUA TESTE</text:p></table:table-cell>
          <table:table-cell><text:p>123</text:p></table:table-cell>
          <table:table-cell><text:p>MOEMA</text:p></table:table-cell>
          <table:table-cell><text:p>Compra e venda</text:p></table:table-cell>
          <table:table-cell><text:p>10/02/2026</text:p></table:table-cell>
          <table:table-cell><text:p>540000</text:p></table:table-cell>
          <table:table-cell><text:p>600000</text:p></table:table-cell>
          <table:table-cell><text:p>1º Cartório de Registro de Imóvel</text:p></table:table-cell>
          <table:table-cell><text:p>80</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:spreadsheet>
  </office:body>
</office:document-content>
XML);
        $zip->close();

        $batch = ItbiImportBatch::create([
            'provider' => 'manual_upload_itbi_sp',
            'source_url' => 'upload://GUIAS DE ITBI PAGAS (2026) ODS (2).ods',
            'source_filename' => 'GUIAS DE ITBI PAGAS (2026) ODS (2).ods',
            'storage_path' => $path,
            'status' => 'downloaded',
            'discovered_at' => now(),
            'downloaded_at' => now(),
        ]);

        app(ItbiTransactionImportService::class)->import($batch);

        $this->assertDatabaseHas('real_estate_transactions', [
            'property_sql' => '001.234.0001-9',
            'street_name' => 'RUA TESTE',
            'neighborhood' => 'MOEMA',
            'transaction_nature' => 'Compra e venda',
            'registry_office' => '1º Cartório de Registro de Imóvel',
            'price_m2' => 6750,
        ]);
    }
}
