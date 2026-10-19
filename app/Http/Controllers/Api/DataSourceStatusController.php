<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Support\ExecutableLocator;
use App\Support\ExternalUrlPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DataSourceStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        abort_unless(auth()->user()?->tenant_id, 403, 'Conta sem empresa vinculada.');

        $transactionCount = Schema::hasTable('real_estate_transactions')
            ? RealEstateTransaction::query()->count()
            : 0;
        $lastImport = Schema::hasTable('itbi_import_batches')
            ? ItbiImportBatch::query()->whereIn('status', ['completed', 'completed_with_errors'])
                ->latest('processing_finished_at')->first()
            : null;

        return response()->json(['data' => [
            $this->portalStatus(),
            $this->item('datazap', 'Mercado', 'DataZAP AVM', 'unavailable', 'API comercial não utilizada. O índice público vem de BCB IVG-R, anúncios do radar e ITBI em dados abertos.'),
            $this->item('fipezap', 'Mercado', 'FipeZAP', 'unavailable', 'API comercial não utilizada. O índice público vem de BCB IVG-R, anúncios do radar e ITBI em dados abertos.'),
            $this->googleStatus(),
            $this->openStreetMapStatus(),
            $this->item(
                'itbi_local',
                'Transações reais',
                'ITBI importado',
                $transactionCount > 0 ? 'connected' : 'no_data',
                $transactionCount > 0 ? 'Base local possui transações importadas.' : 'Pipeline disponível, mas a base local ainda não possui transações.',
                ['record_count' => $transactionCount, 'last_import_at' => $lastImport?->processing_finished_at?->toIso8601String()],
            ),
            $this->publicOrConfigured(
                'cartorios',
                'Transações reais',
                'Cartórios',
                config('services.cartorios.endpoint'),
                'Sem API pública nacional. Transmissões entram pelo ITBI em dados abertos e catálogos CKAN municipais.',
            ),
            $this->publicOrConfigured(
                'itbi_api',
                'Transações reais',
                'ITBI municipal via API',
                config('services.itbi.sp_endpoint') ?: config('services.itbi.endpoint'),
                'Sem endpoint próprio. Catálogos CKAN públicos (SP, Fortaleza, Rio e dados.gov.br) são consultados sem chave.',
            ),
            $this->publicOrConfigured(
                'prefeituras',
                'Transações reais',
                'Prefeituras',
                config('services.prefeituras.endpoint'),
                'Sem endpoint próprio. Catálogos CKAN públicos são consultados quando existirem para a cidade.',
            ),
            $this->item('caixa_leiloes', 'Leilões', 'Caixa', 'not_implemented', 'Conector ainda não implementado.'),
            $this->item('mega_leiloes', 'Leilões', 'Mega Leilões', 'not_implemented', 'Conector ainda não implementado.'),
            $this->item('superbid', 'Leilões', 'Superbid', 'not_implemented', 'Conector ainda não implementado.'),
            $this->item('bcb_sgs', 'Dados econômicos', 'SELIC/CDI', 'available', 'API pública do BCB SGS (séries 11 e 12), sem chave. BrasilAPI entra como fallback.'),
            $this->item('inflation_indexes', 'Dados econômicos', 'INCC/IPCA', 'available', 'API pública do BCB SGS (IPCA 433, INCC-DI 192 e IVG-R 21340), sem chave.'),
        ]]);
    }

    private function portalStatus(): array
    {
        $scriptReady = is_file(base_path('frontend/scripts/portal-scraper.mjs'));
        $node = ExecutableLocator::node(config('services.portal_scraper.node_binary'));
        $browser = ExecutableLocator::browser(config('services.portal_scraper.chrome_path'));
        $runtimeReady = $scriptReady && is_file($node) && $browser !== null && is_file($browser);

        return $this->item(
            'portal_scraper',
            'Mercado',
            'Portais do radar',
            $runtimeReady ? 'available' : 'unavailable',
            $runtimeReady
                ? 'Runtime local encontrado; a disponibilidade de cada portal é confirmada somente durante a busca.'
                : 'Runtime local de Node, navegador ou script do coletor não foi encontrado.',
        );
    }

    private function googleStatus(): array
    {
        $configured = (bool) config('real_estate_data.geocoding.google_enabled')
            && trim((string) config('services.google_maps.key')) !== '';

        return $this->item(
            'google_maps',
            'Geolocalização',
            'Google Maps/Places',
            $configured ? 'configured' : 'pending',
            $configured
                ? 'Credencial configurada; a disponibilidade é confirmada durante a consulta.'
                : 'Credencial não configurada; o sistema usa os fallbacks disponíveis.',
        );
    }

    private function openStreetMapStatus(): array
    {
        $valid = ExternalUrlPolicy::allows(config('services.openstreetmap.nominatim_endpoint'))
            && ExternalUrlPolicy::allows(config('services.openstreetmap.overpass_endpoint'));
        $nominatim = Cache::get('data-source-health:openstreetmap_nominatim');
        $overpass = Cache::get('data-source-health:openstreetmap_overpass');
        $checks = array_values(array_filter([$nominatim, $overpass], 'is_array'));
        $monitoredStatus = collect($checks)->contains(fn (array $item) => ($item['status'] ?? null) === 'unavailable')
            ? 'unavailable'
            : (collect($checks)->contains(fn (array $item) => ($item['status'] ?? null) === 'degraded') ? 'degraded' : 'available');

        return $this->item(
            'openstreetmap',
            'Geolocalização',
            'OpenStreetMap',
            $valid ? ($checks ? $monitoredStatus : 'available') : 'unavailable',
            $valid
                ? ($checks ? 'Estado obtido pelo monitoramento assíncrono.' : 'Endpoints HTTPS configurados; aguardando a primeira verificação assíncrona.')
                : 'Endpoints ausentes ou sem HTTPS.',
            $checks ? ['last_checked_at' => collect($checks)->pluck('checked_at')->filter()->sortDesc()->first()] : [],
        );
    }

    private function publicOrConfigured(string $id, string $group, string $name, mixed $endpoint, string $publicFallback): array
    {
        if (trim((string) $endpoint) === '') {
            return $this->item($id, $group, $name, 'available', $publicFallback);
        }

        return $this->configuredEndpoint($id, $group, $name, $endpoint);
    }

    private function configuredEndpoint(string $id, string $group, string $name, mixed $endpoint): array
    {
        $endpoint = trim((string) $endpoint);
        if ($endpoint === '') {
            return $this->item($id, $group, $name, 'pending', 'Endpoint não configurado.');
        }

        $health = Cache::get('data-source-health:'.$id);
        $health = is_array($health) ? $health : [];
        $configured = ExternalUrlPolicy::allows($endpoint);

        return $this->item(
            $id,
            $group,
            $name,
            $configured ? ($health['status'] ?? 'configured') : 'unavailable',
            $configured
                ? (isset($health['status']) ? 'Estado obtido pelo monitoramento assíncrono.' : 'Endpoint HTTPS configurado; aguardando a primeira verificação assíncrona.')
                : 'O endpoint configurado é inválido ou não usa HTTPS público.',
            isset($health['checked_at']) ? ['last_checked_at' => $health['checked_at']] : [],
        );
    }

    private function item(string $id, string $group, string $name, string $status, string $description, array $details = []): array
    {
        return array_filter([
            'id' => $id,
            'group' => $group,
            'name' => $name,
            'status' => $status,
            'description' => $description,
            'details' => $details ?: null,
        ], fn (mixed $value) => $value !== null);
    }
}
