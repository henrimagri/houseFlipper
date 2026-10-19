<?php

namespace App\Console\Commands;

use App\Support\ExternalUrlPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProbeDataSourcesCommand extends Command
{
    protected $signature = 'data-sources:probe';

    protected $description = 'Atualiza, fora da requisição web, o último estado conhecido das fontes externas';

    public function handle(): int
    {
        $sources = array_filter([
            'openstreetmap_nominatim' => config('services.openstreetmap.nominatim_endpoint'),
            'openstreetmap_overpass' => config('services.openstreetmap.overpass_endpoint'),
            'cartorios' => config('services.cartorios.endpoint'),
            'itbi_api' => config('services.itbi.sp_endpoint') ?: config('services.itbi.endpoint'),
            'prefeituras' => config('services.prefeituras.endpoint'),
        ], fn (mixed $url) => is_string($url) && trim($url) !== '');

        foreach ($sources as $id => $url) {
            $status = 'unavailable';
            if (ExternalUrlPolicy::allows($url)) {
                try {
                    $response = Http::connectTimeout(2)->timeout(5)->withOptions([
                        'allow_redirects' => false,
                        'stream' => true,
                    ])->withHeaders(['User-Agent' => 'FlipControl-Health/1.0'])->get($url);
                    $status = $response->status() < 500 ? 'available' : 'degraded';
                } catch (\Throwable) {
                    $status = 'unavailable';
                }
            }
            $cacheKey = 'data-source-health:'.$id;
            $previous = Cache::get($cacheKey);
            Cache::put($cacheKey, [
                'status' => $status,
                'checked_at' => now()->toIso8601String(),
            ], now()->addHours(2));
            if ($status !== 'available' && data_get($previous, 'status') !== $status) {
                Log::warning('Fonte externa mudou para estado não saudável', [
                    'source_id' => $id,
                    'status' => $status,
                ]);
            }
        }

        $this->info('Estados das fontes atualizados.');

        return self::SUCCESS;
    }
}
