<?php

namespace App\Support;

final class ExecutableLocator
{
    private const CHILD_ENV_ALLOWLIST = [
        'SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP',
        'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'PROGRAMDATA', 'PROGRAMFILES',
        'PROGRAMFILES(X86)', 'HOMEDRIVE', 'HOMEPATH',
    ];

    /**
     * Resolve Node without relying exclusively on the PATH inherited by a long-running server.
     *
     * @param  array<int, string>|null  $candidates
     */
    public static function node(?string $configured = null, ?array $candidates = null): string
    {
        $configured = trim((string) $configured, " \t\n\r\0\x0B\"");
        if ($configured !== '') {
            return $configured;
        }

        $candidates ??= PHP_OS_FAMILY === 'Windows'
            ? array_values(array_filter([
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
                getenv('LOCALAPPDATA') ? rtrim((string) getenv('LOCALAPPDATA'), '\\/').'\\Programs\\nodejs\\node.exe' : null,
            ]))
            : ['/usr/local/bin/node', '/usr/bin/node', '/opt/homebrew/bin/node'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'node';
    }

    /** @param  array<int, string>|null  $candidates */
    public static function browser(?string $configured = null, ?array $candidates = null): ?string
    {
        $configured = trim((string) $configured, " \t\n\r\0\x0B\"");
        if ($configured !== '') {
            return $configured;
        }

        $candidates ??= PHP_OS_FAMILY === 'Windows'
            ? array_values(array_filter([
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
                getenv('LOCALAPPDATA') ? rtrim((string) getenv('LOCALAPPDATA'), '\\/').'\\Google\\Chrome\\Application\\chrome.exe' : null,
            ]))
            : ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build a small, request-independent environment for child runtimes.
     * Symfony intentionally filters the inherited environment in web SAPIs;
     * Node on Windows still needs SystemRoot and related runtime variables.
     *
     * @param  array<string, string|false>  $overrides
     * @param  array<string, mixed>|null  $source
     * @return array<string, string|false>
     */
    public static function childEnvironment(array $overrides = [], ?array $source = null): array
    {
        $source ??= is_array(getenv()) ? getenv() : [];
        $environment = [];

        foreach ($source as $key => $value) {
            if (in_array(strtoupper((string) $key), self::CHILD_ENV_ALLOWLIST, true) && is_scalar($value)) {
                $environment[(string) $key] = (string) $value;
            }
        }

        return array_replace($environment, $overrides);
    }
}
