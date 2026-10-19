<?php

namespace Tests\Unit;

use App\Support\ExternalUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExternalUrlPolicyTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_rejects_non_public_and_ambiguous_destinations(string $url): void
    {
        $this->assertFalse(ExternalUrlPolicy::allows($url));
    }

    public function test_accepts_public_https_destinations(): void
    {
        $this->assertTrue(ExternalUrlPolicy::allows('https://portal.example.test/anuncio?id=1'));
        $this->assertTrue(ExternalUrlPolicy::allows('https://8.8.8.8/item.jpg'));
    }

    public static function unsafeUrls(): array
    {
        return array_map(static fn (string $url): array => [$url], [
            'https://2130706433/admin',
            'https://0x7f000001/admin',
            'https://0177.0.0.1/admin',
            'https://127.1/admin',
            'https://127.0.1/admin',
            'https://0/admin',
            'https://[::1]/admin',
            'https://[::ffff:127.0.0.1]/admin',
        ]);
    }
}
