<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class PostalCodeController extends Controller
{
    private const MAX_RESPONSE_BYTES = 65_536;

    public function __invoke(string $postalCode): JsonResponse
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(5)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions($this->guardedHttpOptions())
                ->get('https://viacep.com.br/ws/'.$postalCode.'/json/');
            $this->assertResponseSize($response);
            if (! $response->successful()) {
                throw new RuntimeException('Provider unavailable');
            }
            $data = $response->json();
            if (! is_array($data)) {
                throw new RuntimeException('Invalid provider response');
            }
            if ($data['erro'] ?? false) {
                return response()->json(['message' => 'CEP não encontrado. Confira os números ou preencha o endereço manualmente.'], 404);
            }
            foreach (['logradouro' => 180, 'bairro' => 120, 'localidade' => 120] as $field => $maxLength) {
                if (! is_string($data[$field] ?? null) || mb_strlen(trim($data[$field])) > $maxLength) {
                    throw new RuntimeException('Invalid provider fields');
                }
            }
            $state = strtoupper(trim((string) ($data['uf'] ?? '')));
            if (! preg_match('/^[A-Z]{2}$/', $state)) {
                throw new RuntimeException('Invalid provider fields');
            }

            return response()->json([
                'postal_code' => $postalCode,
                'street' => trim($data['logradouro']),
                'neighborhood' => trim($data['bairro']),
                'city' => trim($data['localidade']),
                'state' => $state,
            ]);
        } catch (\Throwable) {
            return response()->json(['message' => 'A consulta de CEP está indisponível. Você pode preencher o endereço manualmente.'], 503);
        }
    }

    private function guardedHttpOptions(): array
    {
        return [
            'allow_redirects' => false,
            'decode_content' => false,
            'on_headers' => function (ResponseInterface $response): void {
                $length = trim($response->getHeaderLine('Content-Length'));
                if ($length !== '' && ctype_digit($length) && (int) $length > self::MAX_RESPONSE_BYTES) {
                    throw new RuntimeException('Postal code response exceeds limit.');
                }
            },
            'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > self::MAX_RESPONSE_BYTES || $downloadedBytes > self::MAX_RESPONSE_BYTES) {
                    throw new RuntimeException('Postal code response exceeds limit.');
                }
            },
        ];
    }

    private function assertResponseSize(Response $response): void
    {
        if (strlen($response->body()) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Postal code response exceeds limit.');
        }
    }
}
