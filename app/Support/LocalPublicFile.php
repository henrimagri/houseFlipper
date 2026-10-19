<?php

namespace App\Support;

class LocalPublicFile
{
    private const PUBLIC_EXTENSIONS = ['css', 'js', 'mjs', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'txt', 'webmanifest'];

    private const STORAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'ico'];

    public static function mayServe(string $uri, string $publicPath, string $storagePath): bool
    {
        if (str_contains($uri, "\0") || str_contains($uri, '\\')) {
            return false;
        }
        $file = realpath($publicPath.$uri);
        $publicRoot = realpath($publicPath);
        $storageRoot = realpath($storagePath);

        return $uri !== '/' && $file !== false && $publicRoot !== false && is_file($file)
            && self::isAllowedPath($uri, $file, $publicRoot, $storageRoot ?: null);
    }

    /** All filesystem arguments must already be canonical paths resolved with realpath. */
    public static function isAllowedPath(string $uri, string $file, string $publicRoot, ?string $storageRoot): bool
    {
        if (self::hasHiddenSegment($uri)) {
            return false;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (str_starts_with($uri, '/storage/')) {
            return $storageRoot !== null
                && in_array($extension, self::STORAGE_EXTENSIONS, true)
                && self::within($file, $storageRoot);
        }

        return in_array($extension, self::PUBLIC_EXTENSIONS, true) && self::within($file, $publicRoot);
    }

    private static function hasHiddenSegment(string $uri): bool
    {
        foreach (explode('/', trim($uri, '/')) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    private static function within(string $file, string $root): bool
    {
        $file = str_replace('\\', '/', $file);
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        if (PHP_OS_FAMILY === 'Windows') {
            $file = strtolower($file);
            $root = strtolower($root);
        }

        return str_starts_with($file, $root);
    }
}
