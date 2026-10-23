<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_attribution_requires_consent_and_allows_only_bounded_fields(): void
    {
        $id = (string) Str::uuid();
        $body = ['session_id' => $id, 'consented' => false, 'policy' => 'analytics-v1', 'utm' => ['utm_source' => 'qa']];
        $this->postJson('/api/analytics/visit', $body)->assertUnprocessable();
        $this->assertDatabaseCount('platform_consent_attributions', 0);
        $body['consented'] = true;
        $body['utm']['arbitrary'] = 'private';
        $this->postJson('/api/analytics/visit', $body)->assertOk();
        $this->postJson('/api/analytics/visit', $body)->assertOk();
        $this->assertDatabaseCount('platform_consent_attributions', 1);
        $row = DB::table('platform_consent_attributions')->first();
        $this->assertSame(['utm_source' => 'qa'], json_decode($row->utm, true));
        $this->assertNotSame($id, $row->session_hash);
        $admin = User::factory()->create();
        PlatformAdmin::create(['user_id' => $admin->id, 'email' => $admin->email]);
        $this->platformSession($admin);
        $this->getJson('/api/platform/attribution')->assertOk()->assertJsonPath('data.0.consented_sessions', 1)->assertJsonPath('data.0.linked_registrations', 0);
        $this->getJson('/api/platform/attribution?product=corretor')->assertUnprocessable();
        $body['utm']['utm_source'] = 'name@example.com';
        $this->postJson('/api/analytics/visit', $body)->assertUnprocessable();
    }

    public function test_consent_retention_has_dry_run_and_does_not_delete_recent_or_audits(): void
    {
        foreach ([100, 1] as $days) {
            DB::table('platform_consent_attributions')->insert(['session_hash' => hash('sha256', (string) $days), 'policy' => 'analytics-v1', 'utm' => '{}', 'consented_at' => now()->subDays($days), 'created_at' => now()->subDays($days)]);
        }
        $this->artisan('platform:prune')->assertSuccessful();
        $this->assertDatabaseCount('platform_consent_attributions', 2);
        $this->artisan('platform:prune --execute')->assertSuccessful();
        $this->assertDatabaseCount('platform_consent_attributions', 1);
    }
}
