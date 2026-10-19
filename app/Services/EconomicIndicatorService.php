<?php

namespace App\Services;

use App\Support\ExternalUrlPolicy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class EconomicIndicatorService
{
    private const MAX_BYTES = 262_144;

    private const SERIES = [
        'selic' => ['code' => 11, 'label' => 'SELIC', 'unit' => '% a.a.', 'limit' => 1],
        'cdi' => ['code' => 12, 'label' => 'CDI', 'unit' => '% a.a.', 'limit' => 1],
        'ipca' => ['code' => 433, 'label' => 'IPCA', 'unit' => '% no mês', 'limit' => 1],
        'incc' => ['code' => 192, 'label' => 'INCC-DI', 'unit' => '% no mês', 'limit' => 1],
        'ivg_r' => ['code' => 21340, 'label' => 'IVG-R', 'unit' => 'índice', 'limit' => 1],
    ];

    public function current(): array
    {
        return Cache::remember('economic-indicators:bcb-sgs', now()->addHours(6), fn () => $this->fetch());
    }

    private function fetch(): array
    {
        $items = [];
        foreach (self::SERIES as $key => $series) {
            $items[$key] = $this->seriesFromBcb($key, $series) ?? $this->emptyItem($key, $series);
        }

        if ($items['selic']['value'] === null || $items['cdi']['value'] === null || $items['ipca']['value'] === null) {
            $this->mergeBrasilApi($items);
        }

        $connected = collect($items)->filter(fn (array $item) => $item['value'] !== null)->count();

        return [
            'status' => $connected > 0 ? 'connected' : 'unavailable',
            'source' => $connected > 0 ? 'BCB SGS / BrasilAPI' : 'indisponível',
            'message' => $connected > 0
                ? $connected.' indicador(es) públicos carregados sem chave (BCB SGS).'
                : 'As APIs públicas de SELIC/CDI/IPCA não responderam nesta coleta.',
            'items' => array_values($items),
        ];
    }

    private function seriesFromBcb(string $key, array $series): ?array
    {
        $url = sprintf(
            'https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados/ultimos/%d?formato=json',
            $series['code'],
            $series['limit']
        );

        try {
            $this->assertPublicHttps($url);
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'identity', 'User-Agent' => 'InvestFlip/1.0 (+economic-indicators)'])
                ->withOptions($this->guardedHttpOptions())
                ->get($url);
            $this->assertSize($response);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        $row = collect($response->json())->last();
        if (! is_array($row) || ! isset($row['valor'])) {
            return null;
        }

        $value = (float) str_replace(',', '.', (string) $row['valor']);
        if (! is_finite($value)) {
            return null;
        }

        return $this->item($key, $series, $value, $row['data'] ?? null, 'BCB SGS '.$series['code']);
    }

    private function mergeBrasilApi(array &$items): void
    {
        $url = 'https://brasilapi.com.br/api/taxas/v1';

        try {
            $this->assertPublicHttps($url);
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'identity', 'User-Agent' => 'InvestFlip/1.0 (+economic-indicators)'])
                ->withOptions($this->guardedHttpOptions())
                ->get($url);
            $this->assertSize($response);
        } catch (\Throwable) {
            return;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return;
        }

        $aliases = ['selic' => 'selic', 'cdi' => 'cdi', 'ipca' => 'ipca'];
        foreach ($response->json() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = strtolower((string) ($row['nome'] ?? ''));
            $key = $aliases[$name] ?? null;
            if (! $key || ($items[$key]['value'] ?? null) !== null) {
                continue;
            }
            $value = (float) ($row['valor'] ?? 0);
            if (! is_finite($value) || $value <= 0) {
                continue;
            }
            $items[$key] = $this->item($key, self::SERIES[$key], $value, now()->toDateString(), 'BrasilAPI');
        }
    }

    private function emptyItem(string $key, array $series): array
    {
        return $this->item($key, $series, null, null, null);
    }

    private function item(string $key, array $series, ?float $value, ?string $date, ?string $origin): array
    {
        return [
            'key' => $key,
            'label' => $series['label'],
            'unit' => $series['unit'],
            'value' => $value,
            'date' => $date,
            'origin' => $origin,
        ];
    }

    private function guardedHttpOptions(): array
    {
        return [
            'allow_redirects' => false,
            'decode_content' => false,
            'on_headers' => function (ResponseInterface $response): void {
                $length = trim($response->getHeaderLine('Content-Length'));
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
        ];
    }

    private function assertPublicHttps(string $url): void
    {
        if (! ExternalUrlPolicy::allows($url)) {
            throw new RuntimeException('Endpoint externo não permitido.');
        }
    }

    private function assertSize(Response $response): void
    {
        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException('Resposta externa excede o limite permitido.');
        }
    }
}
