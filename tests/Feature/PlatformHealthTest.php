<?php
namespace Tests\Feature;
use App\Models\{User,PlatformAdmin,PlatformEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Cache};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class PlatformHealthTest extends TestCase {
 use RefreshDatabase;
 public function test_health_uses_only_configured_allowlisted_services_and_never_returns_credentials():void{
  $u=User::factory()->create();PlatformAdmin::create(['user_id'=>$u->id,'email'=>$u->email]);Sanctum::actingAs($u);
  config(['ai.worker.endpoint'=>'http://untrusted.test','ai.worker.token'=>'SECRET_WORKER_TOKEN']);Cache::forget('platform:health');Http::preventStrayRequests();Http::fake(['127.0.0.1:11434/*'=>Http::response([],200)]);
  $this->getJson('/api/platform/health')->assertOk()->assertJsonPath('services.fastapi.status','degraded')->assertDontSee('SECRET_WORKER_TOKEN')->assertDontSee('untrusted.test');
  $this->getJson('/api/platform/performance')->assertOk();$this->getJson('/api/platform/errors')->assertOk();$this->getJson('/api/platform/search?search='.$u->id)->assertOk();
 }
 public function test_percentiles_and_error_grouping_use_real_filtered_values():void{
  $u=User::factory()->create();PlatformAdmin::create(['user_id'=>$u->id,'email'=>$u->email]);Sanctum::actingAs($u);
  foreach([10,20,30,40,100]as$ms)PlatformEvent::create(['category'=>'api','event'=>'api.request.failed','tenant_id'=>9,'route'=>'api/test','duration_ms'=>$ms,'status'=>500]);
  $this->getJson('/api/platform/performance?tenant_id=9')->assertOk()->assertJsonPath('data.0.p50_ms',30)->assertJsonPath('data.0.p95_ms',40)->assertJsonPath('data.0.errors',5);
  $this->getJson('/api/platform/errors?tenant_id=9')->assertOk()->assertJsonPath('data.0.total',5);
 }
}
