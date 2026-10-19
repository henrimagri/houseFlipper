<?php

namespace Tests\Feature;

use App\Services\RealEstateData\ItbiSpreadsheetParserService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetSafetyTest extends TestCase
{
    public function test_duplicate_normalized_headers_are_rejected_instead_of_overwriting_values(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('duplicate.csv', "Valor da Transação;Valor da Transacao\n100000;900000");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cabeçalho duplicado');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows('duplicate.csv'));
    }

    public function test_extra_nonblank_columns_are_rejected_instead_of_silently_discarded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('extra.csv', "SQL;Valor\n123;100000;unexpected");
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('colunas sem cabeçalho');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows('extra.csv'));
    }

    public function test_blank_trailing_columns_and_missing_optional_cells_remain_supported(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('trailing.csv', "SQL;Valor\n123;100000;;\n456");
        $this->assertSame([['sql' => '123', 'valor' => '100000'], ['sql' => '456', 'valor' => null]], iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows('trailing.csv')));
    }

    public function test_csv_handles_utf8_bom_quotes_and_delimiters_inside_values(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('utf8-bom.csv', "\xEF\xBB\xBFSQL;Endereço;Valor\n123;\"Rua A; bloco B\";100000");

        $this->assertSame([
            ['sql' => '123', 'endereco' => 'Rua A; bloco B', 'valor' => '100000'],
        ], iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows('utf8-bom.csv')));
    }

    public function test_csv_handles_utf16_little_endian_with_comma_delimiter(): void
    {
        Storage::fake('local');
        $utf8 = "SQL,Endereço,Valor\r\n456,Alameda São João,200000";
        Storage::disk('local')->put('utf16.csv', "\xFF\xFE".mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8'));

        $this->assertSame([
            ['sql' => '456', 'endereco' => 'Alameda São João', 'valor' => '200000'],
        ], iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows('utf16.csv')));
    }

    private function odsWithRepeatedRows(int $repeat, int $groups = 1): string
    {
        Storage::fake('local');
        $path = 'repeated.ods';
        $xml = '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:spreadsheet><table:table>'
            .'<table:table-row><table:table-cell><text:p>Endereco</text:p></table:table-cell></table:table-row>'
            .str_repeat('<table:table-row table:number-rows-repeated="'.$repeat.'"><table:table-cell><text:p>Rua Teste</text:p></table:table-cell></table:table-row>', $groups)
            .'<table:table-row table:number-rows-repeated="1048576"><table:table-cell/></table:table-row>'
            .'</table:table></office:spreadsheet></office:body></office:document-content>';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE));
        $zip->addFromString('content.xml', $xml);
        $zip->close();

        return $path;
    }

    public function test_ods_repeated_data_rows_are_preserved_and_blank_tail_is_skipped(): void
    {
        config()->set('real_estate_data.itbi_sp.max_ods_expanded_cells', 3);
        $path = $this->odsWithRepeatedRows(3);
        $this->assertSame(array_fill(0, 3, ['endereco' => 'Rua Teste']), iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path)));
    }

    public function test_ods_excessive_nonblank_repetition_is_rejected(): void
    {
        $path = $this->odsWithRepeatedRows(10001);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite de repetição');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
    }

    public function test_ods_expanded_cell_budget_is_enforced_before_yielding_repeated_rows(): void
    {
        config()->set('real_estate_data.itbi_sp.max_ods_expanded_cells', 3);
        $path = $this->odsWithRepeatedRows(2, 2);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite total de células');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
    }

    private function spreadsheet(string $xml): string
    {
        Storage::fake('local');
        $path = 'audit.xlsx';
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return $path;
    }

    public function test_xlsx_inline_strings_are_preserved(): void
    {
        $path = $this->spreadsheet('<worksheet><sheetData><row><c r="A1" t="inlineStr"><is><t>Endereco</t></is></c></row><row><c r="A2" t="inlineStr"><is><t>Rua Teste</t></is></c></row></sheetData></worksheet>');
        $rows = iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
        $this->assertSame([['endereco' => 'Rua Teste']], $rows);
    }

    public function test_xlsx_default_namespace_and_shared_strings_are_supported(): void
    {
        Storage::fake('local');
        $path = 'namespaced.xlsx';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE));
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="'.$namespace.'"><si><t>Endereço</t></si><si><r><t>Rua </t></r><r><t>São João</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="'.$namespace.'"><sheetData><row><c r="A1" t="s"><v>0</v></c></row><row><c r="A2" t="s"><v>1</v></c></row></sheetData></worksheet>');
        $zip->close();

        $this->assertSame([
            ['endereco' => 'Rua São João'],
        ], iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path)));
    }

    public function test_xml_entry_size_is_checked_before_decompression(): void
    {
        $path = $this->spreadsheet('<worksheet>'.str_repeat(' ', 2000).'</worksheet>');
        config()->set('real_estate_data.itbi_sp.max_xml_bytes', 1000);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limite seguro');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
    }

    public function test_xml_dtd_is_rejected(): void
    {
        $path = $this->spreadsheet('<!DOCTYPE worksheet [<!ENTITY test "audit">]><worksheet/>');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DTD');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
    }

    public function test_xlsx_column_index_cannot_allocate_unbounded_array(): void
    {
        $path = $this->spreadsheet('<worksheet><sheetData><row><c r="ZZZZZZ1"><v>1</v></c></row></sheetData></worksheet>');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Coluna XLSX');
        iterator_to_array(app(ItbiSpreadsheetParserService::class)->rows($path));
    }
}
