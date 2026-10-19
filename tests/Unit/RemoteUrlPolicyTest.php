<?php

namespace Tests\Unit;

use App\Support\RemoteUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RemoteUrlPolicyTest extends TestCase
{
    public function test_allowed_https_host_must_resolve_only_to_public_addresses(): void
    {
        (new RemoteUrlPolicy(fn () => ['93.184.216.34', '2606:4700:4700::1111']))->assertAllowedHttps(
            'https://portal.example/listing',
            ['portal.example']
        );

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeDestinations')]
    public function test_unsafe_destinations_are_rejected(string $url, array $hosts, array $addresses): void
    {
        $this->expectException(RuntimeException::class);

        (new RemoteUrlPolicy(fn () => $addresses))->assertAllowedHttps($url, $hosts);
    }

    public static function unsafeDestinations(): array
    {
        return [
            'unlisted host' => ['https://evil.example/', ['portal.example'], ['93.184.216.34']],
            'HTTP downgrade' => ['http://portal.example/', ['portal.example'], ['93.184.216.34']],
            'credentials' => ['https://user:pass@portal.example/', ['portal.example'], ['93.184.216.34']],
            'private IPv4 resolution' => ['https://portal.example/', ['portal.example'], ['10.0.0.5']],
            'metadata resolution' => ['https://portal.example/', ['portal.example'], ['169.254.169.254']],
            'mixed resolution' => ['https://portal.example/', ['portal.example'], ['93.184.216.34', '192.168.1.2']],
            'private IPv6 resolution' => ['https://portal.example/', ['portal.example'], ['fd00::1']],
            'empty DNS result' => ['https://portal.example/', ['portal.example'], []],
        ];
    }
}
