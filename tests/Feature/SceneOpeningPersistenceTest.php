<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SceneOpeningPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): array
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);
        $user = User::create(['tenant_id' => $tenant->id, 'last_product_slug' => 'house_flipper', 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'house_flipper', 'active' => true, 'status' => SubscriptionStatus::Active]);
        $property = Propriedade::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'apelido' => $slug, 'tipo' => 'apartamento', 'status' => 'adquirida']);

        return [$user, '/api/visualizations/propriedades/'.$property->id];
    }

    public function test_openings_reopen_version_and_undo_without_worker_or_other_room_changes(): void
    {
        Http::preventStrayRequests();
        [$user, $url] = $this->account('opening-owner');
        Sanctum::actingAs($user);
        $this->withHeader('X-Product-Context', 'house_flipper');
        $graph = $this->postJson($url.'/rooms', ['type' => 'living', 'furnished' => false, 'dimensions' => ['width' => 4, 'depth' => 5, 'height' => 2.8]])->assertCreated()->json('data.scene_graph');
        $room = $graph['active_room'];
        $action = ['op' => 'add_opening', 'room' => $room, 'side' => 'south', 'kind' => 'window', 'width' => 1.2, 'height' => 1.0, 'offset' => 0.0, 'sill' => 1.0];
        $opened = $this->patchJson($url.'/scene', ['actions' => [$action]])->assertOk()->assertJsonCount(1, 'data.scene_graph.openings')->json('data.scene_graph');
        $opening = $opened['openings'][0];
        $this->assertSame($graph['surfaces'], $opened['surfaces']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.scene_graph.openings.0.id', $opening['id']);
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'undo_scene']]])->assertOk()->assertJsonCount(0, 'data.scene_graph.openings');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'redo_scene']]])->assertOk()->assertJsonPath('data.scene_graph.openings.0.id', $opening['id']);
        $version = $this->postJson($url.'/versions', ['label' => 'Janela original'])->assertCreated()->json('data.id');
        $updated = array_replace($action, ['op' => 'update_opening', 'id' => $opening['id'], 'side' => 'east', 'kind' => 'door', 'sill' => 0, 'height' => 2.1]);
        $this->patchJson($url.'/scene', ['actions' => [$updated]])->assertOk()->assertJsonPath('data.scene_graph.openings.0.kind', 'door')->assertJsonPath('data.scene_graph.openings.0.id', $opening['id']);
        $this->postJson($url.'/versions/'.$version.'/restore')->assertOk()->assertJsonPath('data.scene_graph.openings.0.kind', 'window');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'remove_opening', 'id' => $opening['id']]]])->assertOk()->assertJsonCount(0, 'data.scene_graph.openings');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'undo_scene']]])->assertOk()->assertJsonPath('data.scene_graph.openings.0.id', $opening['id']);
        Http::assertNothingSent();
    }

    public function test_invalid_openings_and_resize_roll_back_all_changes_and_reject_foreign_tenant(): void
    {
        [$user, $url] = $this->account('opening-validation');
        [$outsider] = $this->account('opening-outsider');
        Sanctum::actingAs($user);
        $this->withHeader('X-Product-Context', 'house_flipper');
        $graph = $this->postJson($url.'/rooms', ['type' => 'living', 'dimensions' => ['width' => 4, 'depth' => 5, 'height' => 2.8]])->assertCreated()->json('data.scene_graph');
        $room = $graph['active_room'];
        $action = ['op' => 'add_opening', 'room' => $room, 'side' => 'south', 'kind' => 'window', 'width' => 3.5, 'height' => 1, 'offset' => 0, 'sill' => 1];
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'paint_surface', 'id' => $graph['surfaces'][0]['id'], 'color' => '#123456'], array_replace($action, ['offset' => 1])]])->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('data.scene_graph.surfaces.0.color', $graph['surfaces'][0]['color'])->assertJsonCount(0, 'data.scene_graph.openings');
        $this->patchJson($url.'/scene', ['actions' => [$action]])->assertOk();
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'set_room_dimensions', 'room' => $room, 'dimensions' => ['width' => 3, 'depth' => 5, 'height' => 2.8]]]])->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('data.scene_graph.room_dimensions.'.$room.'.width', 4)->assertJsonCount(1, 'data.scene_graph.openings');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'remove_opening', 'id' => 'unknown']]])->assertUnprocessable();
        $this->patchJson($url.'/scene', ['actions' => [array_replace($action, ['side' => 'ceiling'])]])->assertUnprocessable();
        Sanctum::actingAs($outsider);
        $this->patchJson($url.'/scene', ['actions' => [$action]])->assertNotFound();
    }
}
