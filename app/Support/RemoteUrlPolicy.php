<?php

namespace App\Support;

use RuntimeException;

class RemoteUrlPolicy
{
    public function __construct(private $resolver = null) {}

    public function assertAllowedHttps(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = array_values(array_unique(array_map(
            fn (mixed $allowed): string => strtolower(trim((string) $allowed)),
            $allowedHosts
        )));

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || ! in_array($host, $allowedHosts, true)
            || filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new RuntimeException('Destino remoto não permitido.');
        }

        $addresses = ($this->resolver ?? self::resolve(...))($host);
        if (! is_array($addresses) || $addresses === []) {
            throw new RuntimeException('Não foi possível confirmar o destino remoto.');
        }

        foreach ($addresses as $address) {
            if (! is_string($address)
                || ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Destino remoto não público.');
            }
        }
    }

    private static function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        ))));
    }
}
