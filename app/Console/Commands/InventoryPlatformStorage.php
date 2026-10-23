<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryPlatformStorage extends Command
{
    protected $signature = 'platform:inventory-storage {--limit=10000}';

    protected $description = 'Bounded metadata inventory, potential orphans only; never deletes files';

    public function handle(): int
    {
        $limit = max(1, min(10000, (int) $this->option('limit')));
        $start = microtime(true);
        $count = 0;
        $bytes = 0;
        $truncated = false;
        $references = [];
        foreach (DB::table('property_visualization_assets as a')->join('property_visualizations as v', 'v.id', '=', 'a.property_visualization_id')->select('a.path', 'a.kind', 'v.tenant_id')->limit(20001)->get() as $r) {
            $references[$r->path] = $r;
        }
        foreach (['local', 'public'] as $disk) {
            if (config('filesystems.disks.'.$disk.'.driver') !== 'local') {
                continue;
            }
            $path = Storage::disk($disk)->path('');
            if (! is_dir($path)) {
                continue;
            }
            $base = realpath($path);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink() || ! $file->isFile()) {
                    continue;
                }
                if ($count >= $limit || microtime(true) - $start > config('platform_resources.inventory_seconds', 20)) {
                    $truncated = true;
                    break 2;
                }
                $real = $file->getRealPath();
                if (! $real || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($base) + 1));
                $ref = $references[$relative] ?? null;
                $kind = $ref?->kind ?? match (strtolower($file->getExtension())) {
                    'glb' => 'model','png','jpg','jpeg','webp' => 'image','log' => 'log','pdf','doc','docx' => 'document',default => 'other'
                };
                DB::table('platform_storage_inventory')->updateOrInsert(['disk' => $disk, 'path_hash' => hash_hmac('sha256', $relative, config('app.key'))], ['bytes' => $file->getSize(), 'kind' => substr($kind, 0, 30), 'referenced' => (bool) $ref, 'tenant_id' => $ref?->tenant_id, 'product' => $ref ? 'house_flipper' : null, 'modified_at' => date('Y-m-d H:i:s', $file->getMTime()), 'observed_at' => now()]);
                $bytes += $file->getSize();
                $count++;
            }
        }
        $this->line(json_encode(['files_observed' => $count, 'bytes_observed' => $bytes, 'truncated' => $truncated, 'seconds' => round(microtime(true) - $start, 3), 'delete_executed' => false, 'unknown_reference' => 'Potential orphan only; documents, other modules and paths beyond bounded reference set need manual reconciliation.']));

        return self::SUCCESS;
    }
}
