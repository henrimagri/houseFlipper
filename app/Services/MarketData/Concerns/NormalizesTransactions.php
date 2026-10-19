<?php

namespace App\Services\MarketData\Concerns;

use App\Support\ExternalUrlPolicy;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

trait NormalizesTransactions
{
    private const MAX_PROVIDER_RESPONSE_BYTES = 2_097_152;

    protected function guardedHttpOptions(): array
    {
        return [
            'allow_redirects' => false,
            'decode_content' => false,
            'on_headers' => function (ResponseInterface $response): void {
                $length = trim($response->getHeaderLine('Content-Length'));
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_PROVIDER_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
            'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > self::MAX_PROVIDER_RESPONSE_BYTES || $downloadedBytes > self::MAX_PROVIDER_RESPONSE_BYTES) {
                    throw new RuntimeException('Resposta externa excede o limite permitido.');
                }
            },
        ];
    }

    protected function assertPublicHttpsEndpoint(mixed $endpoint): string
    {
        $endpoint = trim((string) $endpoint);
        if (! ExternalUrlPolicy::allows($endpoint)) {
            throw new RuntimeException('Endpoint externo não permitido.');
        }

        return $endpoint;
    }

    protected function assertGuardedResponseSize(Response $response): void
    {
        if (strlen($response->body()) > self::MAX_PROVIDER_RESPONSE_BYTES) {
            throw new RuntimeException('Resposta externa excede o limite permitido.');
        }
    }

    protected function normalizeTransaction(array $row, string $source, string $type = 'confirmed_transaction'): ?array
    {
        foreach ($row as $value) {
            if (! is_scalar($value) && $value !== null) {
                return null;
            }
        }
        $price = $this->numberFrom($row, ['price', 'valor', 'valor_venda', 'valor_transacao', 'valor_itbi', 'base_calculo', 'vl_transacao', 'valor_de_transacao', 'valor_da_transacao', 'valor_venal', 'valor_venal_de_referencia', 'vl_itbi', 'valor_transacao_rs']);
        $area = $this->numberFrom($row, ['area_m2', 'area', 'area_util', 'area_construida', 'area_privativa', 'area_construida_m2', 'area_privativa_m2', 'area_util_m2', 'area_calculada', 'area_de_calculo']);

        if ($price <= 0 || ! is_finite($price) || ! is_finite($area)) {
            return null;
        }

        return [
            'type' => $type,
            'source' => $source,
            'source_reference' => $row['id'] ?? $row['codigo'] ?? $row['sql'] ?? $row['matricula'] ?? null,
            'title' => $row['title'] ?? $row['titulo'] ?? 'Venda real registrada',
            'address' => $row['address'] ?? $row['endereco'] ?? $row['logradouro'] ?? $row['endereco_aproximado'] ?? null,
            'neighborhood' => $row['neighborhood'] ?? $row['bairro'] ?? $row['nm_bairro'] ?? $row['nome_bairro'] ?? null,
            'city' => $row['city'] ?? $row['cidade'] ?? null,
            'transaction_date' => $row['transaction_date'] ?? $row['data_venda'] ?? $row['data_transacao'] ?? $row['data'] ?? null,
            'price' => round($price, 2),
            'area_m2' => $area > 0 ? round($area, 2) : null,
            'price_m2' => $area > 0 ? round($price / $area, 2) : null,
            'bedrooms' => $this->integerFrom($row, ['bedrooms', 'quartos', 'dormitorios']),
            'bathrooms' => $this->integerFrom($row, ['bathrooms', 'banheiros']),
            'parking_spaces' => $this->integerFrom($row, ['parking_spaces', 'vagas', 'garagens']),
            'similarity_percentage' => $this->integerFrom($row, ['similarity_percentage', 'similaridade']) ?: null,
            'confidence_percentage' => $this->integerFrom($row, ['confidence_percentage', 'confianca']) ?: 80,
            'source_url' => $row['source_url'] ?? $row['url'] ?? null,
            'raw_payload' => $row,
        ];
    }

    protected function numberFrom(array $row, array $keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            $value = (string) $row[$key];
            $value = preg_replace('/[^\d,.-]/', '', $value);
            if (str_contains($value, ',') && str_contains($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (str_contains($value, ',')) {
                $value = str_replace(',', '.', $value);
            }

            return (float) $value;
        }

        return 0;
    }

    protected function integerFrom(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            return (int) preg_replace('/[^\d]/', '', (string) $row[$key]);
        }

        return null;
    }
}
