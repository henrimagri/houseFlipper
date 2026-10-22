<?php
namespace Tests\Feature;
use App\Models\{User,PlatformAdmin,AiToolRun,PlatformEvent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class PlatformWorkloadsTest extends TestCase {
 use RefreshDatabase;
 public function test_ai_uses_scoped_metadata_and_null_cost_instead_of_exposing_private_input():void{
  $u=User::factory()->create();PlatformAdmin::create(['user_id'=>$u->id,'email'=>$u->email]);Sanctum::actingAs($u);
  foreach([1,2]as$t)AiToolRun::create(['user_id'=>$u->id,'tenant_id'=>$t,'product_slug'=>'corretor','agent'=>'broker_assistant','tool'=>'retrieve_knowledge','status'=>'completed','latency_ms'=>20,'input_hash'=>str_repeat('a',64),'input_summary'=>['private'=>'NEVER_EXPOSE_THIS']]);
  $this->getJson('/api/platform/ai?tenant_id=1&product=corretor')->assertOk()->assertJsonPath('data.0.runs',1)->assertJsonPath('data.0.recorded_estimated_cost_usd',null)->assertDontSee('NEVER_EXPOSE_THIS');
  $this->getJson('/api/platform/tools?tenant_id=1')->assertOk()->assertJsonCount(1,'data')->assertDontSee('input_summary');
  $this->getJson('/api/platform/rag?tenant_id=1')->assertOk()->assertJsonPath('queries',1);
  $this->assertSame(2,PlatformEvent::where('event','ai.tool.completed')->count());
 }
}
