<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NeighborhoodContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.google_maps.key', null);
        config()->set('services.openstreetmap.nominatim_endpoint', 'https://nominatim.audit/search');
        config()->set('services.openstreetmap.overpass_endpoint', 'https://overpass.audit/interpreter');
        config()->set('services.openstreetmap.overpass_fallback_endpoint', null);

        $tenant = Tenant::create(['nome' => 'Neighborhood QA', 'slug' => 'neighborhood-qa']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id]), 'sanctum');
    }

    public function test_external_failure_does_not_invent_places_or_distances(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->postJson('/api/neighborhood-context', ['selected' => [
            'neighborhood' => 'Perdizes',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]])->assertOk()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('groups', []);

        $body = $response->getContent();
        $this->assertStringNotContainsString('Perfil urbano estimado', $body);
        $this->assertStringNotContainsString('Terminal/estação', $body);
        $this->assertStringNotContainsString('Escolas próximas', $body);
        $this->assertStringNotContainsString('distance_m', $body);
    }

    public function test_partial_openstreetmap_result_keeps_unreported_categories_empty(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'nominatim.audit/*' => Http::response([['lat' => '-23.5374', 'lon' => '-46.6804']]),
            'overpass.audit/*' => Http::response(['elements' => [[
                'type' => 'node',
                'lat' => -23.5375,
                'lon' => -46.6805,
                'tags' => ['name' => 'Estação auditada', 'railway' => 'station'],
            ]]]),
        ]);

        $response = $this->postJson('/api/neighborhood-context', ['selected' => [
            'neighborhood' => 'Perdizes',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]])->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('source', 'OpenStreetMap')
            ->assertJsonPath('groups.0.count', 1)
            ->assertJsonPath('groups.0.places.0.name', 'Estação auditada');

        foreach (range(1, 4) as $groupIndex) {
            $response->assertJsonPath("groups.$groupIndex.count", 0)
                ->assertJsonPath("groups.$groupIndex.places", []);
        }
    }

    public function test_context_rejects_unknown_subject_keys_and_area_above_domain_limit(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/neighborhood-context', ['selected' => [
            'city' => 'São Paulo',
            'area_m2' => 100000000,
            'unexpected' => 'ignored input',
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors(['selected', 'selected.area_m2']);

        Http::assertNothingSent();
    }

    public function test_context_rejects_private_configured_endpoints_before_egress(): void
    {
        Http::preventStrayRequests();
        config()->set('services.openstreetmap.nominatim_endpoint', 'https://127.0.0.1/search');
        config()->set('services.openstreetmap.overpass_endpoint', 'https://169.254.169.254/interpreter');
        config()->set('services.openstreetmap.overpass_fallback_endpoint', 'http://overpass.example.test/interpreter');
        $requested = [];
        Http::fake(function ($request) use (&$requested) {
            $requested[] = $request->url();

            return Http::response([], 503);
        });

        $this->postJson('/api/neighborhood-context', ['selected' => [
            'neighborhood' => 'Perdizes',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]])->assertOk();

        $this->assertNotEmpty($requested);
        $this->assertFalse(collect($requested)->contains(fn (string $url): bool => str_contains($url, '127.0.0.1') || str_contains($url, '169.254.169.254') || str_starts_with($url, 'http://')));
    }

    public function test_context_disables_redirects_and_rejects_oversized_provider_responses(): void
    {
        Http::preventStrayRequests();
        $observed = [];
        Http::fake(function ($request, $options) use (&$observed) {
            $observed = [
                'encoding' => $request->header('Accept-Encoding')[0] ?? null,
                'redirects' => $options['allow_redirects'] ?? null,
                'decode_content' => $options['decode_content'] ?? null,
            ];

            return Http::response(str_repeat('x', 2_097_153));
        });

        $this->postJson('/api/neighborhood-context', ['selected' => [
            'city' => 'Cidade sem fallback conhecido',
            'state' => 'SP',
        ]])->assertOk()->assertJsonPath('status', 'unavailable');

        $this->assertSame('identity', $observed['encoding']);
        $this->assertFalse($observed['redirects']);
        $this->assertFalse($observed['decode_content']);
    }

    public function test_context_aborts_transfer_when_announced_size_exceeds_limit(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) {
            $options['on_headers'](new PsrResponse(200, ['Content-Length' => '2097153']));

            return Http::response([]);
        });

        $this->postJson('/api/neighborhood-context', ['selected' => [
            'city' => 'Cidade sem fallback conhecido',
            'state' => 'SP',
        ]])->assertOk()->assertJsonPath('status', 'unavailable');
    }

    public function test_context_aborts_transfer_when_progress_exceeds_limit(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) {
            $options['progress'](0, 2_097_153, 0, 0);

            return Http::response([]);
        });

        $this->postJson('/api/neighborhood-context', ['selected' => [
            'city' => 'Cidade sem fallback conhecido',
            'state' => 'SP',
        ]])->assertOk()->assertJsonPath('status', 'unavailable');
    }
}
