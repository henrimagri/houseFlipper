<?php

namespace App\Services\Marketplace;

use App\Models\AuctionImport;
use App\Models\AuctionListing;
use App\Models\User;
use App\Services\RealEstateData\ItbiSpreadsheetParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuctionImportService
{
    public function __construct(private readonly ItbiSpreadsheetParserService $parser) {}

    public function import(User $user, UploadedFile $file, string $provider): AuctionImport
    {
        $path = $file->storeAs('auction-imports', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        $batch = AuctionImport::query()->create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'provider' => $provider,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'status' => 'processing',
        ]);
        $total = $imported = $rejected = 0;
        try {
            $this->removeProviderPreamble($path, $provider);
            foreach ($this->parser->rows($path) as $row) {
                if (++$total > 50000) throw new \RuntimeException('O arquivo excede o limite de 50.000 registros.');
                $normalized = $this->normalize($row, $provider);
                if ($normalized === null) { $rejected++; continue; }
                AuctionListing::query()->updateOrCreate(
                    ['provider' => $provider, 'source_id' => $normalized['source_id']],
                    $normalized + ['provider' => $provider, 'collected_at' => now()],
                );
                $imported++;
            }
            $batch->update(['status' => $rejected ? 'completed_with_errors' : 'completed', 'rows_total' => $total, 'rows_imported' => $imported, 'rows_rejected' => $rejected, 'finished_at' => now()]);
        } catch (\Throwable $exception) {
            $batch->update(['status' => 'failed', 'rows_total' => $total, 'rows_imported' => $imported, 'rows_rejected' => $rejected, 'error_summary' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
            throw $exception;
        } finally {
            Storage::disk('local')->delete($path);
        }
        return $batch->fresh();
    }

    public function syncOfficialState(User $user, string $state): AuctionImport
    {
        $state = strtoupper($state);
        if (preg_match('/^[A-Z]{2}$/', $state) !== 1) {
            throw new \InvalidArgumentException('Informe uma UF válida.');
        }

        $url = "https://venda-imoveis.caixa.gov.br/listaweb/Lista_imoveis_{$state}.csv";
        $response = Http::connectTimeout(10)->timeout(40)->retry(2, 400)->withUserAgent('FlipControl/1.0')->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException('A fonte oficial da CAIXA não respondeu. Tente novamente em alguns minutos.');
        }
        $body = $response->body();
        if ($body === '' || strlen($body) > 20 * 1024 * 1024) {
            throw new \RuntimeException('O arquivo oficial retornado está vazio ou excede o limite de 20 MB.');
        }

        $temporary = 'auction-imports/official-'.Str::uuid().'.csv';
        Storage::disk('local')->put($temporary, $body);
        try {
            $file = new UploadedFile(Storage::disk('local')->path($temporary), "Lista_imoveis_{$state}.csv", 'text/csv', null, true);

            return $this->import($user, $file, 'caixa');
        } finally {
            Storage::disk('local')->delete($temporary);
        }
    }

    private function normalize(array $row, string $provider): ?array
    {
        $get = fn (array $keys) => collect($keys)->map(fn ($key) => $row[$key] ?? null)->first(fn ($value) => trim((string) $value) !== '');
        $sourceId = trim((string) $get(['numero_do_imovel', 'n_do_imovel', 'id', 'codigo', 'identificador', 'numero_imovel']));
        $title = trim((string) $get(['endereco', 'titulo', 'imovel', 'descricao']));
        if ($sourceId === '' || $title === '') return null;
        $state = strtoupper(trim((string) $get(['uf', 'estado'])));
        $notice = $this->officialUrl($get(['edital', 'link_edital', 'url_edital']), $provider);
        return [
            'source_id' => Str::limit($sourceId, 180, ''), 'title' => Str::limit($title, 255, ''),
            'property_type' => $this->propertyType($get(['tipo_de_imovel', 'tipo', 'tipologia']), $get(['descricao']), 60),
            'city' => $this->text($get(['cidade', 'municipio']), 120), 'state' => preg_match('/^[A-Z]{2}$/', $state) ? $state : null,
            'neighborhood' => $this->text($get(['bairro']), 120),
            'price_cents' => $this->money($get(['valor_de_venda', 'preco', 'valor_minimo', 'lance_minimo'])),
            'appraisal_cents' => $this->money($get(['valor_de_avaliacao', 'avaliacao'])),
            'sale_method' => $this->text($get(['modalidade_de_venda', 'modalidade']), 120),
            'status' => strtolower($this->text($get(['situacao', 'status']), 40) ?? 'available'),
            'official_url' => $this->officialUrl($get(['link', 'url', 'link_oficial', 'link_de_acesso']), $provider), 'notice_url' => $notice,
            'source_updated_at' => null, 'source_payload' => array_slice($row, 0, 80, true),
        ];
    }

    private function money(mixed $value): ?int
    {
        $raw = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if ($raw === '') return null;
        if (str_contains($raw, ',')) $raw = str_replace(['.', ','], ['', '.'], $raw);
        $number = filter_var($raw, FILTER_VALIDATE_FLOAT);
        return $number !== false && $number >= 0 ? (int) round($number * 100) : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : Str::limit($text, $max, '');
    }

    private function propertyType(mixed $value, mixed $description, int $max): ?string
    {
        $type = $this->text($value, $max);
        if ($type !== null) {
            return $type;
        }
        $first = trim(explode(',', (string) $description, 2)[0] ?? '');

        return $this->text($first, $max);
    }

    private function removeProviderPreamble(string $path, string $provider): void
    {
        if ($provider !== 'caixa' || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            return;
        }
        $disk = Storage::disk('local');
        $raw = $disk->get($path);
        $lines = preg_split('/\R/', $raw) ?: [];
        foreach (array_slice($lines, 0, 20, true) as $index => $line) {
            $utf8 = mb_convert_encoding((string) $line, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
            $normalized = Str::of($utf8)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->toString();
            if (str_contains($normalized, 'do_imovel') && str_contains($normalized, 'cidade') && str_contains($normalized, 'modalidade')) {
                if ($index > 0) {
                    $disk->put($path, implode(PHP_EOL, array_slice($lines, $index)));
                }

                return;
            }
        }
    }

    private function officialUrl(mixed $value, string $provider): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) return null;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = $provider === 'caixa' ? ['caixa.gov.br'] : ['vendasgov.serpro.gov.br', 'gov.br'];
        return collect($allowed)->contains(fn ($domain) => $host === $domain || str_ends_with($host, '.'.$domain)) ? $url : null;
    }
}
