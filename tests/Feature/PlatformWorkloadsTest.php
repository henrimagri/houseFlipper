<?php

namespace Tests\Feature;

use App\Models\AiToolRun;
use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\PropertyProcessingJob;
use App\Models\PropertyVisualization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformWorkloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_uses_scoped_metadata_and_null_cost_instead_of_exposing_private_input(): void
    {
        $u = User::factory()->create();
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        Sanctum::actingAs($u);
        foreach ([1, 2] as $t) {
            AiToolRun::create(['user_id' => $u->id, 'tenant_id' => $t, 'product_slug' => 'corretor', 'agent' => 'broker_assistant', 'tool' => 'retrieve_knowledge', 'status' => 'completed', 'latency_ms' => 20, 'input_hash' => str_repeat('a', 64), 'input_summary' => ['private' => 'NEVER_EXPOSE_THIS']]);
        }
        $this->getJson('/api/platform/ai?tenant_id=1&product=corretor')->assertOk()->assertJsonPath('data.0.runs', 1)->assertJsonPath('data.0.recorded_estimated_cost_usd', null)->assertDontSee('NEVER_EXPOSE_THIS');
        $this->getJson('/api/platform/tools?tenant_id=1')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('input_summary');
        $this->getJson('/api/platform/rag?tenant_id=1')->assertOk()->assertJsonPath('queries', 1);
        $this->assertSame(2, PlatformEvent::where('event', 'ai.tool.completed')->count());
    }

    public function test_job_listing_does_not_expose_result_artifact_paths_or_fabricate_metrics(): void
    {
        $u = User::factory()->create();
        PlatformAdmin::create(['user_id' => $u->id, 'email' => $u->email]);
        Sanctum::actingAs($u);
        $v = PropertyVisualization::create(['tenant_id' => 8, 'user_id' => $u->id, 'subject_type' => 'propriedades', 'subject_id' => 1, 'status' => 'ready', 'level' => 'preview']);
        PropertyProcessingJob::create(['property_visualization_id' => $v->id, 'tenant_id' => 8, 'user_id' => $u->id, 'kind' => 'photogrammetry', 'status' => 'completed', 'result' => ['actual_method' => 'tsdf', 'private_path' => 'SECRET_PRIVATE_ARTIFACT', 'geometry_quality' => ['final_model_eligible' => false]]]);
        $this->getJson('/api/platform/jobs?tenant_id=8')->assertOk()->assertJsonPath('data.0.actual_method', 'tsdf')->assertJsonPath('data.0.quality.final_model_eligible', false)->assertJsonPath('data.0.quality.precision_10cm', null)->assertDontSee('SECRET_PRIVATE_ARTIFACT');
        $this->getJson('/api/platform/jobs?product=corretor')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/platform/storage')->assertOk();
        $this->getJson('/api/platform/queues')->assertOk();
    }
}
