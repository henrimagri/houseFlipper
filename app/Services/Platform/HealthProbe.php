<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthProbe
{
    public function snapshot(): array
    {
        return Cache::remember('platform:health', 30, function () {
            $services = ['laravel' => ['status' => 'online', 'version' => app()->version()]];
            try {
                DB::select('SELECT 1');
                $services['database'] = ['status' => 'online', 'driver' => DB::connection()->getDriverName(), 'last_migration' => DB::table('migrations')->orderByDesc('id')->value('migration')];
            } catch (\Throwable) {
                $services['database'] = ['status' => 'offline'];
            }
            foreach (['ollama' => ['ai.local', '/api/tags', true], 'qdrant' => ['ai.vector', '/healthz', (bool) config('ai.vector.enabled')], 'fastapi' => ['ai.worker', '/health', filled(config('ai.worker.token'))]] as $name => [$cfg,$path,$enabled]) {
                $url = rtrim((string) config($cfg.'.endpoint'), '/');
                $host = parse_url($url, PHP_URL_HOST);
                if (! $enabled || ! $url) {
                    $services[$name] = ['status' => 'not_configured'];

                    continue;
                }
                if (! in_array($host, config($cfg.'.allowed_hosts', []), true) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
                    $services[$name] = ['status' => 'degraded', 'reason' => 'host_not_allowed'];

                    continue;
                }
                try {
                    $http = Http::acceptJson()->connectTimeout(1)->timeout(2)->withOptions(['allow_redirects' => false]);
                    if ($name === 'fastapi') {
                        $http = $http->withHeaders(['X-Worker-Token' => config('ai.worker.token')]);
                    }if ($name === 'qdrant' && config('ai.vector.api_key')) {
                        $http = $http->withHeaders(['api-key' => config('ai.vector.api_key')]);
                    }$response = $http->get($url.$path);
                    $services[$name] = ['status' => $response->successful() ? 'online' : 'degraded', 'http_status' => $response->status()];
                    if ($name === 'fastapi' && $response->successful()) {
                        $body = $response->json();
                        $services[$name]['cuda_available'] = $body['torch']['cuda_available'] ?? null;
                        $services[$name]['device'] = $body['torch']['device'] ?? null;
                    }
                } catch (\Throwable) {
                    $services[$name] = ['status' => 'offline'];
                }
            }
            $root = storage_path('app');
            $services['storage'] = ['status' => is_writable($root) ? 'online' : 'degraded', 'free_bytes' => @disk_free_space($root) ?: null, 'total_bytes' => @disk_total_space($root) ?: null];
            $services['queue'] = ['status' => config('queue.default') === 'sync' ? 'degraded' : 'not_configured', 'driver' => config('queue.default'), 'note' => 'Contagem da fila não comprova worker vivo; heartbeat ainda não configurado.'];

            return ['services' => $services, 'checked_at' => now()->toIso8601String(), 'uptime' => null, 'note' => 'Snapshot atual com cache 30 s; não é histórico de uptime.'];
        });
    }
}
