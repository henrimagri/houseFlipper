<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Services\RealEstateData\ItbiTransactionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ItbiImportPipelineTest extends TestCase
{
    use RefreshDatabase;

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
