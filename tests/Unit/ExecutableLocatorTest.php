<?php

namespace Tests\Unit;

use App\Support\ExecutableLocator;
use PHPUnit\Framework\TestCase;

class ExecutableLocatorTest extends TestCase
{
    public function test_configured_node_binary_has_priority(): void
    {
        $this->assertSame('C:\\custom\\node.exe', ExecutableLocator::node('  "C:\\custom\\node.exe"  ', [PHP_BINARY]));
    }

    public function test_existing_platform_candidate_is_used_before_path_fallback(): void
    {
        $this->assertSame(PHP_BINARY, ExecutableLocator::node(null, ['Z:\\missing-node.exe', PHP_BINARY]));
        $this->assertSame('node', ExecutableLocator::node(null, ['Z:\\missing-node.exe']));
    }

    public function test_browser_resolution_is_explicit_and_returns_null_when_unavailable(): void
    {
        $this->assertSame(PHP_BINARY, ExecutableLocator::browser(null, ['Z:\\missing-browser.exe', PHP_BINARY]));
        $this->assertSame('C:\\custom\\chrome.exe', ExecutableLocator::browser('"C:\\custom\\chrome.exe"', []));
        $this->assertNull(ExecutableLocator::browser(null, ['Z:\\missing-browser.exe']));
    }

    public function test_child_environment_keeps_runtime_variables_and_drops_request_data(): void
    {
        $environment = ExecutableLocator::childEnvironment(
            ['CHROME_PATH' => 'C:\\browser.exe'],
            ['SystemRoot' => 'C:\\Windows', 'PATH' => 'C:\\bin', 'HTTP_AUTHORIZATION' => 'Bearer secret', 'REQUEST_URI' => '/private']
        );

        $this->assertSame('C:\\Windows', $environment['SystemRoot']);
        $this->assertSame('C:\\bin', $environment['PATH']);
        $this->assertSame('C:\\browser.exe', $environment['CHROME_PATH']);
        $this->assertArrayNotHasKey('HTTP_AUTHORIZATION', $environment);
        $this->assertArrayNotHasKey('REQUEST_URI', $environment);
    }
}
