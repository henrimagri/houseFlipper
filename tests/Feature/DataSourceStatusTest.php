<?php

namespace Tests\Feature;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSourceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        $tenant = Tenant::create(['nome' => 'Status QA', 'slug' => 'status-qa-'.uniqid()]);

        return User::factory()->create(array_replace(['tenant_id' => $tenant->id], $overrides));
    }

    public function test_status_requires_an_active_verified_account_with_tenant(): void
    {
        $this->getJson('/api/data-sources/status')->assertUnauthorized();

        $unverified = $this->user(['requires_email_verification' => true, 'email_verified_at' => null]);
        $this->actingAs($unverified, 'sanctum')->getJson('/api/data-sources/status')
            ->assertForbidden()->assertJsonPath('code', 'email_verification_required');

        $tenantless = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($tenantless, 'sanctum')->getJson('/api/data-sources/status')->assertForbidden();

        $inactive = $this->user(['ativo_flg' => false]);
        $this->actingAs($inactive, 'sanctum')->getJson('/api/data-sources/status')->assertUnauthorized();
    }

    public function test_status_reports_runtime_configuration_without_exposing_secrets_or_endpoints(): void
    {
        config()->set('services.google_maps.key', 'secret-google-key');
        config()->set('services.cartorios.endpoint', 'https://private.example.test/cartorios');
        config()->set('services.cartorios.token', 'secret-cartorio-token');
        config()->set('services.itbi.sp_endpoint', 'http://insecure.example.test/itbi');

        $response = $this->actingAs($this->user(), 'sanctum')->getJson('/api/data-sources/status')
            ->assertOk()->assertJsonCount(14, 'data')
            ->assertJsonFragment(['id' => 'google_maps', 'status' => 'configured'])
            ->assertJsonFragment(['id' => 'cartorios', 'status' => 'configured'])
            ->assertJsonFragment(['id' => 'itbi_api', 'status' => 'unavailable'])
            ->assertJsonFragment(['id' => 'bcb_sgs', 'status' => 'available'])
            ->assertJsonFragment(['id' => 'itbi_local', 'status' => 'no_data']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret-google-key', $body);
        $this->assertStringNotContainsString('secret-cartorio-token', $body);
        $this->assertStringNotContainsString('private.example.test', $body);
        $this->assertStringNotContainsString('insecure.example.test', $body);
    }

    public function test_local_itbi_status_uses_real_record_count_and_latest_import(): void
    {
        $batch = ItbiImportBatch::create([
            'provider' => 'qa',
            'source_url' => 'https://example.test/itbi.csv',
            'source_filename' => 'itbi.csv',
            'status' => 'completed',
            'processing_finished_at' => '2026-10-17 10:00:00',
        ]);
        RealEstateTransaction::create([
            'import_batch_id' => $batch->id,
            'source_name' => 'QA',
            'source_reference' => 'qa-1',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $items = $this->actingAs($this->user(), 'sanctum')->getJson('/api/data-sources/status')
            ->assertOk()->json('data');
        $itbi = collect($items)->firstWhere('id', 'itbi_local');

        $this->assertSame('connected', $itbi['status']);
        $this->assertSame(1, $itbi['details']['record_count']);
        $this->assertStringStartsWith('2026-10-17T10:00:00', $itbi['details']['last_import_at']);
    }

    public function test_status_does_not_report_private_or_local_endpoints_as_available(): void
    {
        config()->set('services.cartorios.endpoint', 'https://127.0.0.1/cartorios');
        config()->set('services.itbi.sp_endpoint', 'https://169.254.169.254/itbi');
        config()->set('services.prefeituras.endpoint', 'https://prefeitura.local/api');
        config()->set('services.openstreetmap.nominatim_endpoint', 'https://localhost/search');
        config()->set('services.openstreetmap.overpass_endpoint', 'https://10.0.0.1/interpreter');

        $response = $this->actingAs($this->user(), 'sanctum')->getJson('/api/data-sources/status')->assertOk();

        foreach (['cartorios', 'itbi_api', 'prefeituras', 'openstreetmap'] as $id) {
            $item = collect($response->json('data'))->firstWhere('id', $id);
            $this->assertSame('unavailable', $item['status'], $id);
        }
    }
}
