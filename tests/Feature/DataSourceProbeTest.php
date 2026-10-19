<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataSourceProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_runs_outside_web_request_and_status_endpoint_reads_cached_result(): void
    {
        config()->set('services.cartorios.endpoint', 'https://8.8.8.8/health');
        config()->set('services.openstreetmap.nominatim_endpoint', 'https://8.8.8.8/nominatim');
        config()->set('services.openstreetmap.overpass_endpoint', 'https://8.8.8.8/overpass');
        Http::fake(['*' => Http::response([], 200)]);

        $this->artisan('data-sources:probe')->assertSuccessful();
        Http::assertSentCount(3);
        $this->assertSame('available', data_get(Cache::get('data-source-health:cartorios'), 'status'));

        $tenant = Tenant::create(['nome' => 'Health QA', 'slug' => 'health-qa']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/data-sources/status')->assertOk();
        $cartorios = collect($response->json('data'))->firstWhere('id', 'cartorios');
        $this->assertSame('available', $cartorios['status']);
        $this->assertArrayHasKey('last_checked_at', $cartorios['details']);
    }

    public function test_probe_rejects_unsafe_endpoint_without_network_request(): void
    {
        config()->set('services.cartorios.endpoint', 'http://169.254.169.254/latest/meta-data');
        config()->set('services.openstreetmap.nominatim_endpoint', '');
        config()->set('services.openstreetmap.overpass_endpoint', '');
        Http::fake();

        $this->artisan('data-sources:probe')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame('unavailable', data_get(Cache::get('data-source-health:cartorios'), 'status'));
    }
}
