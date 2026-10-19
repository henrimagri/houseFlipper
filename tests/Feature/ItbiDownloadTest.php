<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Services\RealEstateData\ItbiFileDownloadService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItbiDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('real_estate_data.itbi_sp.allowed_download_hosts', ['audit.test']);
    }

    public function test_untrusted_urls_are_blocked_before_any_request(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response('fake')]);
        foreach (['https://untrusted.test/data.csv', 'https://audit.test.evil.test/data.csv', 'http://audit.test/data.csv', 'https://127.0.0.1/data.csv', 'https://user:pass@audit.test/data.csv', 'https://audit.test:8443/data.csv'] as $url) {
            $batch = $this->batch();
            $batch->update(['source_url' => $url]);
            try {
                app(ItbiFileDownloadService::class)->download($batch);
                $this->fail('Untrusted destination must be rejected.');
            } catch (\RuntimeException) {
                $this->assertSame('failed', $batch->refresh()->status);
            }
        }
        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_redirect_to_untrusted_destination_is_not_requested(): void
    {
        Storage::fake('local');
        Http::fake(['audit.test/*' => Http::response('', 302, ['Location' => 'https://untrusted.test/data.csv'])]);
        $batch = $this->batch();
        try {
            app(ItbiFileDownloadService::class)->download($batch);
            $this->fail('Redirect must be validated.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $batch->refresh()->status);
        }
        Http::assertSentCount(1);
    }

    public function test_trusted_relative_redirect_saves_final_file(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://audit.test/data.csv' => Http::response('', 302, ['Location' => '/final.csv']),
            'https://audit.test/final.csv' => Http::response('SQL;Valor'),
        ]);
        $batch = app(ItbiFileDownloadService::class)->download($this->batch());
        $this->assertSame('SQL;Valor', Storage::disk('local')->get($batch->storage_path));
        Http::assertSentCount(2);
    }

    public function test_redirect_loop_has_a_finite_limit(): void
    {
        Storage::fake('local');
        Http::fake(['audit.test/*' => Http::response('', 302, ['Location' => '/data.csv'])]);
        $batch = $this->batch();
        try {
            app(ItbiFileDownloadService::class)->download($batch);
            $this->fail('Redirect loop must fail.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $batch->refresh()->status);
        }
        Http::assertSentCount(4);
    }

    private function batch(): ItbiImportBatch
    {
        return ItbiImportBatch::create(['provider' => 'audit', 'source_url' => 'https://audit.test/data.csv', 'source_filename' => 'data.csv', 'status' => 'discovered']);
    }

    public function test_failed_storage_write_does_not_mark_batch_downloaded(): void
    {
        Http::fake(['audit.test/*' => Http::response('SQL;Valor')]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->andReturn($disk);
        $batch = $this->batch();
        $error = null;
        try {
            app(ItbiFileDownloadService::class)->download($batch);
        } catch (\RuntimeException $exception) {
            $error = $exception;
        }
        $this->assertNotNull($error, 'Write failure must stop the download pipeline.');
        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertNull($batch->storage_path);
    }

    public function test_connection_failure_marks_batch_failed_without_exposing_provider_details(): void
    {
        Http::fake(fn () => throw new \RuntimeException('PRIVATE_AUDIT_MARKER'));
        $batch = $this->batch();
        try {
            app(ItbiFileDownloadService::class)->download($batch);
            $this->fail('Connection failure must be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('PRIVATE_AUDIT_MARKER', $exception->getMessage());
        }
        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertStringNotContainsString('PRIVATE_AUDIT_MARKER', $batch->error_message);
    }

    public function test_download_saves_actual_bytes_hash_and_reuses_unchanged_file(): void
    {
        Storage::fake('local');
        $contents = "SQL;Valor\n123;100000";
        Http::fake(['audit.test/*' => Http::response($contents)]);
        $batch = app(ItbiFileDownloadService::class)->download($this->batch());
        $this->assertSame('downloaded', $batch->status);
        $this->assertSame(hash('sha256', $contents), $batch->file_hash);
        $this->assertSame($contents, Storage::disk('local')->get($batch->storage_path));
        $path = $batch->storage_path;
        $batch = app(ItbiFileDownloadService::class)->download($batch);
        $this->assertSame($path, $batch->storage_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_database_failure_removes_only_the_newly_downloaded_file(): void
    {
        Storage::fake('local');
        Http::fake(['audit.test/*' => Http::response('SQL;Valor')]);
        $batch = $this->batch();
        $this->rejectDownloadedStatusUpdates();

        try {
            app(ItbiFileDownloadService::class)->download($batch);
            $this->fail('Database failure must abort the download.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $batch->refresh()->status);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_itbi_downloaded_update');
        }

        $this->assertNull($batch->refresh()->storage_path);
        $this->assertNull($batch->file_hash);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_database_failure_preserves_a_preexisting_content_addressed_file(): void
    {
        Storage::fake('local');
        $contents = 'SQL;Valor';
        Http::fake(['audit.test/*' => Http::response($contents)]);
        $path = trim(config('real_estate_data.itbi_sp.storage_path'), '/').'/data-'.hash('sha256', $contents).'.csv';
        Storage::disk('local')->put($path, $contents);
        $batch = $this->batch();
        $this->rejectDownloadedStatusUpdates();

        try {
            app(ItbiFileDownloadService::class)->download($batch);
            $this->fail('Database failure must abort the download.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $batch->refresh()->status);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_itbi_downloaded_update');
        }

        $this->assertNull($batch->refresh()->storage_path);
        $this->assertNull($batch->file_hash);
        $this->assertSame($contents, Storage::disk('local')->get($path));
        $this->assertSame([$path], Storage::disk('local')->allFiles());
    }

    private function rejectDownloadedStatusUpdates(): void
    {
        DB::statement(<<<'SQL'
            CREATE TRIGGER reject_itbi_downloaded_update
            BEFORE UPDATE ON itbi_import_batches
            WHEN NEW.status = 'downloaded'
            BEGIN
                SELECT RAISE(ABORT, 'simulated database failure');
            END
        SQL);
    }
}
