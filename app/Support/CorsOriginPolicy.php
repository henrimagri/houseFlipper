<?php

namespace App\Support;

class CorsOriginPolicy
{
    public static function normalize(array $origins): array
    {
        $normalized = [];

        foreach ($origins as $origin) {
            $origin = trim((string) $origin);
            if ($origin === '' || $origin === '*') {
                continue;
            }

            $parts = parse_url($origin);
            if (! is_array($parts)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])
                || (isset($parts['path']) && ! in_array($parts['path'], ['', '/'], true))) {
                continue;
            }

            $scheme = strtolower((string) $parts['scheme']);
            $host = strtolower((string) $parts['host']);
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';
            $normalized[] = $scheme.'://'.$host.$port;
        }

        return array_values(array_unique($normalized));
    }
}
