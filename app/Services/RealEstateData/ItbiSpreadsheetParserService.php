<?php

namespace App\Services\RealEstateData;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ItbiSpreadsheetParserService
{
    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function rows(string $storagePath): \Generator
    {
        $disk = config('real_estate_data.itbi_sp.storage_disk', 'local');
        $absolutePath = Storage::disk($disk)->path($storagePath);
        $extension = Str::lower(pathinfo($storagePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            yield from $this->csvRows($absolutePath);

            return;
        }

        if ($extension === 'xlsx') {
            yield from $this->xlsxRows($absolutePath);

            return;
        }

        if ($extension === 'ods') {
            yield from $this->odsRows($absolutePath);

            return;
        }

        throw new \InvalidArgumentException("Formato não suportado nesta fase: {$extension}");
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new \RuntimeException('Não foi possível abrir CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return;
        }

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $headers = null;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array_map(fn ($value) => is_string($value) ? trim(mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252')) : $value, $row);

            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            if ($this->isBlankRow($row)) {
                continue;
            }

            yield $this->combine($headers, $row);
        }

        fclose($handle);
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function xlsxRows(string $path): \Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Não foi possível abrir XLSX.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            throw new \RuntimeException('XLSX sem sheet1.xml.');
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new \RuntimeException('XLSX inválido.');
        }

        $headers = null;
        foreach ($xml->sheetData->row as $rowNode) {
            $row = $this->xlsxRowValues($rowNode, $sharedStrings);

            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            if ($this->isBlankRow($row)) {
                continue;
            }

            yield $this->combine($headers, $row);
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $strings = simplexml_load_string($xml);
        if ($strings === false) {
            return [];
        }

        $values = [];
        foreach ($strings->si as $item) {
            if (isset($item->t)) {
                $values[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $values[] = $text;
        }

        return $values;
    }

    private function xlsxRowValues(\SimpleXMLElement $rowNode, array $sharedStrings): array
    {
        $values = [];
        foreach ($rowNode->c as $cell) {
            $reference = (string) ($cell['r'] ?? '');
            $index = $this->columnIndex($reference);
            $type = (string) ($cell['t'] ?? '');
            $raw = (string) ($cell->v ?? '');
            $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
            $values[$index] = trim($value);
        }

        if ($values === []) {
            return [];
        }

        ksort($values);

        return array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values);
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function odsRows(string $path): \Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Não foi possível abrir ODS.');
        }

        $contentXml = $zip->getFromName('content.xml');
        $zip->close();

        if (! $contentXml) {
            throw new \RuntimeException('ODS sem content.xml.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contentXml);
        if ($xml === false) {
            $error = collect(libxml_get_errors())->first();
            libxml_clear_errors();

            throw new \RuntimeException('ODS inválido'.($error ? ': '.trim($error->message) : ' (bytes: '.strlen((string) $contentXml).').'));
        }
        libxml_clear_errors();

        $namespaces = $xml->getNamespaces(true);
        $officeNamespace = $namespaces['office'] ?? 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
        $tableNamespace = $namespaces['table'] ?? 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

        $body = $xml->children($officeNamespace)->body ?? null;
        $spreadsheet = $body?->children($officeNamespace)->spreadsheet ?? null;
        if (! $spreadsheet) {
            throw new \RuntimeException('ODS sem planilha.');
        }

        $tables = $spreadsheet->children($tableNamespace)->table ?? [];
        $firstTable = null;
        foreach ($tables as $table) {
            $firstTable = $table;
            break;
        }

        if (! $firstTable) {
            throw new \RuntimeException('ODS sem aba de dados.');
        }

        $headers = null;
        foreach ($firstTable->children($tableNamespace)->{'table-row'} as $rowNode) {
            $repeatRows = $this->odsRepeatedCount($rowNode, 'number-rows-repeated');
            if ($repeatRows > 1 && $headers !== null) {
                continue;
            }

            $row = $this->odsRowValues($rowNode, $namespaces);
            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            yield $this->combine($headers, $row);
        }
    }

    private function odsRowValues(\SimpleXMLElement $rowNode, array $namespaces): array
    {
        $tableNamespace = $namespaces['table'] ?? 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
        $officeNamespace = $namespaces['office'] ?? 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
        $textNamespace = $namespaces['text'] ?? 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

        $values = [];
        foreach ($rowNode->children($tableNamespace)->{'table-cell'} as $cell) {
            $repeatColumns = min($this->odsRepeatedCount($cell, 'number-columns-repeated'), 1000);
            $value = $this->odsCellValue($cell, $officeNamespace, $textNamespace);

            for ($i = 0; $i < $repeatColumns; $i++) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function odsCellValue(\SimpleXMLElement $cell, string $officeNamespace, string $textNamespace): string
    {
        $officeAttributes = $cell->attributes($officeNamespace);
        $value = (string) ($officeAttributes['value'] ?? $officeAttributes['date-value'] ?? $officeAttributes['time-value'] ?? '');

        if ($value !== '') {
            return trim($value);
        }

        $paragraphs = [];
        foreach ($cell->children($textNamespace)->p as $paragraph) {
            $paragraphs[] = trim((string) $paragraph);
        }

        return trim(implode(' ', array_filter($paragraphs, fn ($text) => $text !== '')));
    }

    private function odsRepeatedCount(\SimpleXMLElement $node, string $attribute): int
    {
        $namespaces = $node->getNamespaces(true);
        $tableNamespace = $namespaces['table'] ?? 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
        $attributes = $node->attributes($tableNamespace);

        return max(1, (int) ($attributes[$attribute] ?? 1));
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/([A-Z]+)/i', $reference, $match);
        $letters = strtoupper($match[1] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header) => Str::of((string) $header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(), $headers);
    }

    private function combine(array $headers, array $row): array
    {
        $row = array_pad($row, count($headers), null);

        return array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty();
    }
}
