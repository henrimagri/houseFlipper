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

class EditableRoomTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): array
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);
        $user = User::create(['tenant_id' => $tenant->id, 'last_product_slug' => 'house_flipper', 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'house_flipper', 'active' => true, 'status' => SubscriptionStatus::Active]);
        $property = Propriedade::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'apelido' => 'QA room', 'tipo' => 'apartamento', 'status' => 'adquirida']);

        return [$user, '/api/visualizations/propriedades/'.$property->id];
    }

    public function test_room_without_photos_can_be_edited_undone_reopened_and_versioned(): void
    {
        Http::preventStrayRequests();
        [$user, $url] = $this->account('room-owner');
        Sanctum::actingAs($user);
        $this->withHeader('X-Product-Context', 'house_flipper');
        $graph = $this->postJson($url.'/rooms', ['type' => 'living', 'furnished' => true, 'dimensions' => ['width' => 4, 'depth' => 5, 'height' => 2.8]])
            ->assertCreated()->assertJsonPath('data.scene_graph.fidelity', 'parametric')->assertJsonCount(6, 'data.scene_graph.surfaces')->json('data.scene_graph');
        $room = $graph['active_room'];
        $this->assertSame('user', $graph['room_dimensions'][$room]['source']);
        $wallId = $graph['surfaces'][0]['id'];
        $originalColor = $graph['surfaces'][0]['color'];
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'paint_surface', 'id' => $wallId, 'color' => '#123456']]])
            ->assertOk()->assertJsonPath('data.scene_graph.surfaces.0.color', '#123456')->assertJsonPath('data.scene_graph.surfaces.1.color', $originalColor);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.scene_graph.editor_history');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'undo_scene']]])->assertOk()->assertJsonPath('data.scene_graph.surfaces.0.color', $originalColor);
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'redo_scene']]])->assertOk()->assertJsonPath('data.scene_graph.surfaces.0.color', '#123456');
        $version = $this->postJson($url.'/versions', ['label' => 'Parede azul'])->assertCreated()->json('data.id');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'paint_surface', 'id' => $wallId, 'color' => '#aabbcc', 'finish' => 'satin']]])->assertOk();
        $this->getJson($url.'/versions/'.$version)->assertOk()->assertJsonPath('data.graph.surfaces.0.color', '#123456');
        $this->getJson($url)->assertOk()->assertJsonPath('data.scene_graph.surfaces.0.color', '#aabbcc')->assertJsonPath('data.scene_graph.surfaces.0.finish', 'satin');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'add_object', 'kind' => 'chair', 'room' => $room]]])->assertOk()->assertJsonCount(5, 'data.scene_graph.objects');
        $this->patchJson($url.'/scene', ['actions' => [['op' => 'undo_scene']]])->assertOk()->assertJsonCount(4, 'data.scene_graph.objects');
        $this->postJson($url.'/versions/'.$version.'/restore')->assertOk()->assertJsonPath('data.scene_graph.surfaces.0.color', '#123456');
        $this->postJson($url.'/rooms', ['type' => 'office', 'dimensions' => ['width' => 3, 'depth' => 4, 'height' => 2.5]])->assertCreated()->assertJsonCount(2, 'data.scene_graph.rooms')->assertJsonPath('data.scene_graph.surfaces.0.color', '#123456');
        $this->assertDatabaseCount('property_visualization_assets', 0);
    }

    public function test_room_creation_rejects_foreign_tenant_and_invalid_dimensions(): void
    {
        [$owner, $url] = $this->account('owned-room');
        [$outsider] = $this->account('other-room');
        $input = ['type' => 'living', 'dimensions' => ['width' => 4, 'depth' => 5, 'height' => 2.8]];
        Sanctum::actingAs($outsider);
        $this->withHeader('X-Product-Context', 'house_flipper')->postJson($url.'/rooms', $input)->assertNotFound();
        Sanctum::actingAs($owner);
        $input['dimensions']['height'] = 100;
        $this->postJson($url.'/rooms', $input)->assertUnprocessable();
        $this->assertDatabaseCount('property_visualizations', 0);
    }

    public function test_version_preview_is_scoped_to_the_owned_project(): void
    {
        [$owner, $url] = $this->account('preview-owner');
        [$outsider, $otherUrl] = $this->account('preview-other');
        $input = ['type' => 'living', 'dimensions' => ['width' => 4, 'depth' => 5, 'height' => 2.8]];
        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'house_flipper')->postJson($url.'/rooms', $input)->assertCreated();
        $version = $this->postJson($url.'/versions', ['label' => 'Owned version'])->assertCreated()->json('data.id');
        Sanctum::actingAs($outsider);
        $this->getJson($url.'/versions/'.$version)->assertNotFound();
        $this->postJson($otherUrl.'/rooms', $input)->assertCreated();
        $this->getJson($otherUrl.'/versions/'.$version)->assertNotFound();
        $this->postJson($otherUrl.'/versions/'.$version.'/restore')->assertNotFound();
    }
}
