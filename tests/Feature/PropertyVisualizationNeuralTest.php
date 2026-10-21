<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\PropertyVisualization;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyVisualizationNeuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiview_photos_are_uploaded_in_one_rate_limited_request(): void
    {
        Storage::fake('local');
        [$owner, $property] = $this->accountWithProperty('batch-owner');
        Sanctum::actingAs($owner);
        $files = collect(range(1, 8))->map(fn (int $index) => UploadedFile::fake()->create("view-{$index}.jpg", 80, 'image/jpeg'))->all();

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->post('/api/visualizations/propriedades/'.$property->id.'/assets', [
                'kind' => 'photo',
                'arquivos' => $files,
            ])->assertCreated()->assertJsonCount(8, 'data');

        $this->assertDatabaseCount('property_visualization_assets', 8);
    }

    public function test_real_world_glb_can_use_the_dedicated_model_size_limit(): void
    {
        Storage::fake('local');
        config()->set('visualizations.max_image_kb', 1024);
        config()->set('visualizations.max_model_kb', 32768);
        [$owner, $property] = $this->accountWithProperty('large-model-owner');
        Sanctum::actingAs($owner);
        $model = UploadedFile::fake()->createWithContent(
            'captured-room.glb',
            'glTF'.str_repeat("\0", 2 * 1024 * 1024)
        );

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->post('/api/visualizations/propriedades/'.$property->id.'/assets', [
                'kind' => 'glb',
                'arquivo' => $model,
            ])->assertCreated()
            ->assertJsonPath('data.kind', 'glb');

        $this->assertDatabaseHas('property_visualization_assets', [
            'original_name' => 'captured-room.glb',
            'kind' => 'glb',
            'is_primary' => true,
        ]);
    }

    public function test_inferior_reconstruction_candidate_does_not_replace_existing_primary_model(): void
    {
        Storage::fake('local');
        config()->set('ai.worker.token', 'test-worker-token');
        [$owner, $property] = $this->accountWithProperty('reconstruction-gate-owner');
        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id)->assertOk();
        $visualization = PropertyVisualization::query()->where('subject_id', $property->id)->firstOrFail();
        Storage::disk('local')->put('visualizations/phase8.glb', 'phase-8-model');
        $primary = $visualization->assets()->create([
            'kind' => 'glb', 'room_key' => 'reconstruction_preview', 'path' => 'visualizations/phase8.glb',
            'original_name' => 'phase8.glb', 'mime_type' => 'model/gltf-binary', 'size_bytes' => 13,
            'checksum' => hash('sha256', 'phase-8-model'), 'is_primary' => true,
        ]);
        $photos = collect(range(1, 8))->map(function (int $index) use ($visualization) {
            $path = "visualizations/capture-{$index}.jpg";
            Storage::disk('local')->put($path, "photo-{$index}");

            return $visualization->assets()->create([
                'kind' => 'photo', 'path' => $path, 'original_name' => "capture-{$index}.jpg",
                'mime_type' => 'image/jpeg', 'size_bytes' => 7,
                'checksum' => hash('sha256', "photo-{$index}"),
            ]);
        });
        $jobId = '11111111-1111-4111-8111-111111111111';
        Http::fake(function ($request) use ($jobId) {
            if (str_ends_with($request->url(), '/v1/3d/reconstruct')) {
                return Http::response([
                    'job_id' => $jobId,
                    'status' => 'completed',
                    'artifacts' => ['glb' => 'depth-fusion.glb'],
                    'geometry_quality' => [
                        'viewer_eligible' => true,
                        'final_model_eligible' => false,
                        'reasons' => ['Completude inferior ao modelo ativo.'],
                    ],
                    'limitations' => ['Candidato experimental não promovido.'],
                ]);
            }

            return Http::response('candidate-model', 200, ['Content-Type' => 'model/gltf-binary']);
        });

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->postJson('/api/visualizations/propriedades/'.$property->id.'/reconstruct', [
                'asset_ids' => $photos->pluck('id')->all(),
                'capture_confirmed' => true,
                'method' => 'tsdf',
            ])->assertOk();

        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertDatabaseHas('property_visualization_assets', [
            'property_visualization_id' => $visualization->id,
            'original_name' => 'reconstruction-preview.glb',
            'is_primary' => false,
        ]);
        $candidate = $visualization->assets()->where('original_name', 'reconstruction-preview.glb')->firstOrFail();
        $job = $visualization->processingJobs()->latest('id')->firstOrFail();
        $this->assertFalse($job->result['promotion']['promoted']);
        $this->assertTrue($job->result['promotion']['previous_primary_preserved']);
        $this->assertSame($candidate->id, $job->result['promotion']['candidate_asset_id']);

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id)
            ->assertOk()
            ->assertJsonPath('data.model.id', $primary->id)
            ->assertJsonPath('data.model.diagnostic_preview', false)
            ->assertJsonPath('data.diagnostic_models.0.id', $candidate->id);

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id.'?diagnostic_asset_id='.$candidate->id)
            ->assertOk()
            ->assertJsonPath('data.model.id', $candidate->id)
            ->assertJsonPath('data.model.diagnostic_preview', true)
            ->assertJsonPath('data.model.viewer_eligible', true);

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id.'?diagnostic_asset_id='.$primary->id)
            ->assertNotFound();
    }

    public function test_neural_artifacts_are_private_and_associated_with_the_owned_property(): void
    {
        Storage::fake('local');
        [$owner, $property] = $this->accountWithProperty('vision-owner');
        [$foreign] = $this->accountWithProperty('vision-foreign');

        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id)->assertOk();
        $visualization = PropertyVisualization::query()->where('subject_id', $property->id)->firstOrFail();
        Storage::disk('local')->put('visualizations/input.jpg', 'authorized-image');
        $asset = $visualization->assets()->create([
            'kind' => 'photo', 'path' => 'visualizations/input.jpg', 'original_name' => 'sala.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 16, 'checksum' => hash('sha256', 'authorized-image'),
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v1/vision/analyze')) {
                return Http::response([
                    'job_id' => '00000000-0000-0000-0000-000000000001',
                    'status' => 'completed',
                    'depth' => ['artifact' => 'depth.png', 'model' => 'Depth Anything V2 Small'],
                    'segmentation' => ['artifact' => 'mask.png', 'overlay_artifact' => 'segmentation-overlay.png', 'model' => 'SAM 2.1 Hiera Tiny', 'dimensions' => ['width' => 960, 'height' => 640]],
                    'detection' => ['model' => 'DETR ResNet-50', 'objects' => [['label' => 'couch', 'confidence' => 0.98, 'box_xyxy' => [400, 220, 900, 580]]]],
                    'structured_result' => ['visible_elements' => [['label' => 'couch', 'confidence' => 0.98]], 'limitations' => ['profundidade relativa']],
                ]);
            }

            return Http::response('generated-private-artifact', 200, ['Content-Type' => 'image/png']);
        });

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->postJson('/api/visualizations/propriedades/'.$property->id.'/vision', ['asset_id' => $asset->id])
            ->assertOk()
            ->assertJsonPath('data.processing_jobs.0.status', 'completed')
            ->assertJsonPath('data.photo_analyses.0.room_guess', 'living')
            ->assertJsonPath('data.photo_analyses.0.detection_confidence', 98)
            ->assertJsonPath('data.photo_analyses.0.room_provenance.is_neural_room_classifier', false)
            ->assertJsonPath('data.scene_graph.objects.0.kind', 'sofa')
            ->assertJsonPath('data.scene_graph.objects.0.approximate_from_2d', true);
        $this->assertDatabaseCount('property_processing_jobs', 1);
        $this->assertDatabaseHas('property_visualization_assets', ['property_visualization_id' => $visualization->id, 'kind' => 'depth_map']);
        $this->assertDatabaseHas('property_visualization_assets', ['property_visualization_id' => $visualization->id, 'kind' => 'segmentation_mask']);
        $this->assertDatabaseHas('property_photo_analyses', [
            'subject_id' => $property->id,
            'room_guess' => 'living',
            'detection_confidence' => 98,
            'room_confidence' => 90,
        ]);

        Sanctum::actingAs($foreign);
        $this->withHeader('X-Product-Context', 'house_flipper')
            ->postJson('/api/visualizations/propriedades/'.$property->id.'/vision', ['asset_id' => $asset->id])
            ->assertNotFound();
    }

    public function test_missing_object_evidence_is_reported_as_unclassified_without_zero_room_confidence(): void
    {
        Storage::fake('local');
        [$owner, $property] = $this->accountWithProperty('unclassified-owner');
        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id)->assertOk();
        $visualization = PropertyVisualization::query()->where('subject_id', $property->id)->firstOrFail();
        Storage::disk('local')->put('visualizations/unknown.jpg', 'authorized-image');
        $asset = $visualization->assets()->create([
            'kind' => 'photo', 'path' => 'visualizations/unknown.jpg', 'original_name' => 'imagem-001.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 16, 'checksum' => hash('sha256', 'authorized-image'),
        ]);
        Http::fake(['127.0.0.1:8010/v1/vision/analyze' => Http::response([
            'job_id' => '00000000-0000-0000-0000-000000000002',
            'status' => 'completed',
            'depth' => ['model' => 'Depth test'],
            'segmentation' => ['model' => 'SAM test', 'dimensions' => ['width' => 960, 'height' => 640]],
            'detection' => ['model' => 'DETR test', 'objects' => []],
            'structured_result' => ['visible_elements' => [], 'limitations' => []],
        ])]);

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->postJson('/api/visualizations/propriedades/'.$property->id.'/vision', ['asset_id' => $asset->id])
            ->assertOk()
            ->assertJsonPath('data.photo_analyses.0.room_guess', null)
            ->assertJsonPath('data.photo_analyses.0.confidence', null)
            ->assertJsonPath('data.photo_analyses.0.detection_confidence', null)
            ->assertJsonPath('data.photo_analyses.0.room_confidence', null)
            ->assertJsonPath('data.scene_graph.active_room', 'unclassified')
            ->assertJsonPath('data.scene_graph.rooms.0.classification_status', 'unclassified');

        $this->assertDatabaseHas('property_photo_analyses', [
            'subject_id' => $property->id,
            'room_guess' => null,
            'confidence' => null,
            'detection_confidence' => null,
            'room_confidence' => null,
        ]);
    }

    public function test_authorized_user_can_create_a_private_masked_photo_simulation(): void
    {
        Storage::fake('local');
        [$owner, $property] = $this->accountWithProperty('simulation-owner');
        Sanctum::actingAs($owner);
        $this->withHeader('X-Product-Context', 'house_flipper')
            ->getJson('/api/visualizations/propriedades/'.$property->id)->assertOk();
        $visualization = PropertyVisualization::query()->where('subject_id', $property->id)->firstOrFail();
        Storage::disk('local')->put('visualizations/simulation-input.jpg', 'authorized-image');
        $asset = $visualization->assets()->create([
            'kind' => 'photo', 'path' => 'visualizations/simulation-input.jpg', 'original_name' => 'sala.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 16, 'checksum' => hash('sha256', 'authorized-image'),
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v1/vision/simulate')) {
                return Http::response([
                    'job_id' => '00000000-0000-0000-0000-000000000003',
                    'status' => 'completed',
                    'simulation_artifact' => 'simulation.png',
                    'comparison_artifact' => 'comparison.png',
                    'segmentation' => ['artifact' => 'mask.png', 'overlay_artifact' => 'segmentation-overlay.png'],
                    'composition' => ['color' => '#7892ad', 'method' => 'luminance_preserving_masked_recolor'],
                ]);
            }

            return Http::response('private-generated-image', 200, ['Content-Type' => 'image/png']);
        });

        $this->withHeader('X-Product-Context', 'house_flipper')
            ->postJson('/api/visualizations/propriedades/'.$property->id.'/photo-simulations', [
                'asset_id' => $asset->id,
                'color' => '#7892AD',
                'opacity' => 0.82,
                'points' => [
                    ['x' => 420, 'y' => 180, 'label' => 1],
                    ['x' => 310, 'y' => 340, 'label' => 0],
                ],
            ])->assertOk()
            ->assertJsonPath('data.processing_jobs.0.kind', 'photo_simulation')
            ->assertJsonPath('data.processing_jobs.0.status', 'completed');

        foreach (['segmentation_mask', 'vision_overlay', 'photo_simulation', 'photo_comparison'] as $kind) {
            $this->assertDatabaseHas('property_visualization_assets', [
                'property_visualization_id' => $visualization->id,
                'kind' => $kind,
                'room_key' => 'source_'.$asset->id,
            ]);
        }
    }

    private function accountWithProperty(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create([
            'tenant_id' => $tenant->id, 'last_product_slug' => 'house_flipper', 'name' => $slug,
            'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password',
        ]);
        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id, 'product_slug' => 'house_flipper', 'active' => true,
            'status' => SubscriptionStatus::Active,
        ]);
        $property = Propriedade::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'apelido' => 'Imóvel '.$slug, 'tipo' => 'apartamento',
            'area_m2' => 72, 'quartos' => 2, 'banheiros' => 1, 'vagas' => 1, 'status' => 'adquirida',
        ]);

        return [$user, $property];
    }
}
