<?php

namespace Tests\Unit;

use App\Services\PropertyPortalScraper;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PortalScraperLogRedactionTest extends TestCase
{
    public function test_failed_browser_metadata_does_not_copy_stderr_into_logs(): void
    {
        $private = 'PRIVATE_BROWSER_STDERR https://example.test/?query=sensitive';
        $process = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, '.var_export($private, true).'); exit(9);']);
        $process->run();
        $method = new ReflectionMethod(PropertyPortalScraper::class, 'failedProcessContext');
        $context = $method->invoke(app(PropertyPortalScraper::class), $process);

        $this->assertSame(9, $context['exit_code']);
        $this->assertSame(strlen($private), $context['stderr_bytes']);
        $this->assertSame(hash('sha256', $private), $context['stderr_hash']);
        $this->assertStringNotContainsString('PRIVATE_BROWSER_STDERR', json_encode($context));
        $this->assertStringNotContainsString('sensitive', json_encode($context));
    }
}
