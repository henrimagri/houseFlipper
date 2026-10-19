<?php

namespace Tests\Unit;

use App\Support\LocalPublicFile;
use PHPUnit\Framework\TestCase;

class LocalPublicFileTest extends TestCase
{
    public function test_explicit_public_storage_target_is_allowed_only_under_storage_url(): void
    {
        $public = '/workspace/public';
        $storage = '/workspace/storage/app/public';
        $this->assertTrue(LocalPublicFile::isAllowedPath('/storage/photo.jpg', $storage.'/photo.jpg', $public, $storage));
        $this->assertFalse(LocalPublicFile::isAllowedPath('/other/photo.jpg', $storage.'/photo.jpg', $public, $storage));
        $this->assertFalse(LocalPublicFile::isAllowedPath('/storage/photo.jpg', $storage.'/photo.jpg', $public, null));
    }

    public function test_sibling_prefix_private_files_and_other_symlink_targets_are_denied(): void
    {
        foreach (['/workspace/public-backup/file.txt', '/workspace/storage/app/private/file.txt', '/outside/file.txt', '/workspace/storage/app/public-backup/file.txt'] as $file) {
            $this->assertFalse(LocalPublicFile::isAllowedPath('/storage/file.txt', $file, '/workspace/public', '/workspace/storage/app/public'));
        }
        $this->assertTrue(LocalPublicFile::isAllowedPath('/logo.png', '/workspace/public/logo.png', '/workspace/public', null));
    }

    public function test_invalid_null_bytes_and_backslash_paths_are_rejected_without_filesystem_errors(): void
    {
        $this->assertFalse(LocalPublicFile::mayServe("/bad\0path", '/unused', '/unused'));
        $this->assertFalse(LocalPublicFile::mayServe('/storage\\private.txt', '/unused', '/unused'));
    }

    public function test_storage_only_delegates_supported_assets_and_downloads_to_static_server(): void
    {
        foreach (['php', 'phtml', 'html', 'svg', 'js', 'pdf', 'csv', 'json', 'xlsx', 'docx', 'zip', 'mp4'] as $extension) {
            $this->assertFalse(LocalPublicFile::isAllowedPath('/storage/file.'.$extension, '/workspace/storage/app/public/file.'.$extension, '/workspace/public', '/workspace/storage/app/public'));
        }
        $this->assertFalse(LocalPublicFile::isAllowedPath('/index.php', '/workspace/public/index.php', '/workspace/public', '/workspace/storage/app/public'));
        $this->assertTrue(LocalPublicFile::isAllowedPath('/storage/photo.WEBP', '/workspace/storage/app/public/photo.WEBP', '/workspace/public', '/workspace/storage/app/public'));
    }

    public function test_public_root_serves_only_explicit_web_assets_and_never_hidden_files(): void
    {
        $public = '/workspace/public';

        foreach (['/.user.ini', '/.htaccess', '/assets/.private/logo.png', '/config.php', '/dump.sql', '/page.html'] as $uri) {
            $this->assertFalse(LocalPublicFile::isAllowedPath($uri, $public.'/'.$uri, $public, null));
        }

        foreach (['/robots.txt', '/favicon.ico', '/assets/app.css', '/assets/app.js', '/assets/logo.svg', '/fonts/app.woff2'] as $uri) {
            $this->assertTrue(LocalPublicFile::isAllowedPath($uri, $public.'/'.$uri, $public, null));
        }
    }
}
