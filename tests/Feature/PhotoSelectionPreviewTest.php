<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\PropertyVisualization;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoSelectionPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function setupPhoto(string $slug = 'selection'): array
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);
        $user = User::create(['tenant_id' => $tenant->id, 'last_product_slug' => 'house_flipper', 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::create(['tenant_id' => $tenant->id, 'product_slug' => 'house_flipper', 'active' => true, 'status' => SubscriptionStatus::Active]);
        $property = Propriedade::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'apelido' => $slug, 'tipo' => 'apartamento', 'area_m2' => 72, 'quartos' => 2, 'banheiros' => 1, 'vagas' => 1, 'status' => 'adquirida']);
        Sanctum::actingAs($user);
        $url = '/api/visualizations/propriedades/'.$property->id;
        $this->withHeader('X-Product-Context', 'house_flipper')->getJson($url)->assertOk();
        $visual = PropertyVisualization::where('subject_id', $property->id)->firstOrFail();
        $path = 'visualizations/'.$slug.'.png';
        Storage::disk('local')->put($path, $this->png());
        $photo = $visual->assets()->create(['kind' => 'photo', 'path' => $path, 'original_name' => 'photo.png', 'mime_type' => 'image/png', 'size_bytes' => strlen($this->png()), 'checksum' => hash('sha256', $this->png())]);

        return [$url, $visual, $photo, $user];
    }

    private function png(): string
    {
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 0, 0, 0, 0)).$chunk('IDAT', gzcompress("\0\xff\xff\0\xff\xff")).$chunk('IEND', '');
    }

    private function fakeWorker(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/v1/vision/segment')) {
                return Http::response(['job_id' => '00000000-0000-0000-0000-000000000031', 'status' => 'completed', 'segmentation' => ['artifact' => 'mask.png', 'overlay_artifact' => 'segmentation-overlay.png', 'dimensions' => ['width' => 2, 'height' => 2], 'prompt_points' => [[1, 1]], 'prompt_labels' => [1]]]);
            }
            if (str_ends_with($request->url(), '/v1/vision/simulate')) {
                return Http::response(['job_id' => '00000000-0000-0000-0000-000000000032', 'status' => 'completed', 'simulation_artifact' => 'simulation.png', 'comparison_artifact' => 'comparison.png', 'segmentation' => ['artifact' => 'mask.png'], 'composition' => ['color' => '#123456', 'opacity' => 0.8, 'material' => 'paint', 'finish' => 'satin']]);
            }

            return Http::response($this->png(), 200, ['Content-Type' => 'image/png']);
        });
    }

    private function select(string $url, int $photoId): array
    {
        return $this->postJson($url.'/photo-selections', ['asset_id' => $photoId, 'points' => [['x' => 1, 'y' => 1, 'label' => 1], ['x' => 0, 'y' => 0, 'label' => 0]]])->assertOk()->json('data');
    }

    public function test_neural_mask_preview_confirmation_and_reopening_preserve_edit_state_without_reinference(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo] = $this->setupPhoto();
        $this->fakeWorker();
        $selection = $this->select($url, $photo->id);
        $this->assertSame(['width' => 2, 'height' => 2], $selection['dimensions']);
        $stroke = ['mode' => 'erase', 'radius' => 0.05, 'points' => [['x' => 0.3, 'y' => 0.4]]];
        $preview = $this->postJson($url.'/photo-simulations', ['asset_id' => $photo->id, 'color' => '#123456', 'mask_mode' => 'refined', 'base_mask_asset_id' => $selection['mask_asset_id'], 'mask_strokes' => [$stroke], 'finish' => 'satin', 'preview' => true])->assertOk()->assertJsonPath('data.finish', 'satin')->json('data');
        $this->assertNotNull($preview['preview_id']);
        $this->assertSame($selection['mask_url'], $preview['base_mask_url']);
        $this->getJson($url)->assertJsonCount(0, 'data.photo_editing.simulations');
        $count = Http::recorded()->count();
        $confirm = $url.'/photo-simulations/'.$preview['preview_id'].'/confirm';
        $this->postJson($confirm)->assertOk()->assertJsonPath('data.preview_id', null);
        $this->postJson($confirm)->assertOk();
        $history = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.photo_editing.simulations')->json('data.photo_editing.simulations.0');
        $this->assertSame([$stroke], $history['mask_strokes']);
        $this->assertSame($selection['mask_asset_id'], $history['base_mask_asset_id']);
        $this->assertSame('satin', $history['finish']);
        $this->assertSame($selection['points'], $history['points']);
        $this->assertSame($selection['dimensions'], $history['dimensions']);
        $this->assertNotNull($history['mask_url']);
        $job = $visual->processingJobs()->where('kind', 'photo_simulation')->firstOrFail();
        $this->assertSame($selection['points'], $job->result['editor_input']['selection_points']);
        // Versions saved before selection_points was introduced recover from the same source selection.
        $legacyResult = $job->result;
        unset($legacyResult['editor_input']['selection_points'], $legacyResult['editor_input']['selection_dimensions']);
        $job->update(['result' => $legacyResult]);
        $legacyHistory = $this->getJson($url)->assertOk()->json('data.photo_editing.simulations.0');
        $this->assertSame($selection['points'], $legacyHistory['points']);
        $this->assertSame($selection['dimensions'], $legacyHistory['dimensions']);
        $this->assertSame($count, Http::recorded()->count());
        $this->assertSame($this->png(), Storage::disk('local')->get($photo->path));
        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/v1/vision/simulate')) {
                return false;
            }
            $parts = collect($request->data())->keyBy('name');

            return $parts['base_mask']['contents'] === $this->png() && $parts['mask_mode']['contents'] === 'refined' && $parts['finish']['contents'] === 'satin' && empty($parts['points']['contents']);
        });
    }

    public function test_refined_mask_requires_matching_photo_and_completed_neural_selection(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo] = $this->setupPhoto();
        $this->fakeWorker();
        $selection = $this->select($url, $photo->id);
        $mask = $visual->assets()->findOrFail($selection['mask_asset_id']);
        $input = ['asset_id' => $photo->id, 'color' => '#123456', 'mask_mode' => 'refined', 'base_mask_asset_id' => $mask->id, 'preview' => true];
        $requests = Http::recorded()->count();
        $mask->update(['room_key' => 'source_999']);
        $this->postJson($url.'/photo-simulations', $input)->assertNotFound();
        $mask->update(['room_key' => 'source_'.$photo->id]);
        $visual->processingJobs()->where('kind', 'photo_selection')->update(['status' => 'failed']);
        $this->postJson($url.'/photo-simulations', $input)->assertUnprocessable();
        $this->assertSame($requests, Http::recorded()->count());
    }

    public function test_invalid_mask_is_rejected_before_composition_and_cross_tenant_requests_are_denied(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo] = $this->setupPhoto();
        $this->fakeWorker();
        $selection = $this->select($url, $photo->id);
        $mask = $visual->assets()->findOrFail($selection['mask_asset_id']);
        Storage::disk('local')->put($mask->path, 'invalid image');
        $input = ['asset_id' => $photo->id, 'color' => '#123456', 'mask_mode' => 'refined', 'base_mask_asset_id' => $mask->id, 'preview' => true];
        $requests = Http::recorded()->count();
        $this->postJson($url.'/photo-simulations', $input)->assertUnprocessable();
        [$otherUrl] = $this->setupPhoto('other');
        $this->postJson($url.'/photo-selections', ['asset_id' => $photo->id, 'points' => [['x' => 1, 'y' => 1, 'label' => 1]]])->assertNotFound();
        $this->postJson($otherUrl.'/photo-simulations', $input)->assertNotFound();
        $this->assertSame($requests, Http::recorded()->count());
    }

    public function test_preview_discard_is_idempotent_and_cannot_be_confirmed(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo] = $this->setupPhoto();
        $this->fakeWorker();
        $selection = $this->select($url, $photo->id);
        $preview = $this->postJson($url.'/photo-simulations', ['asset_id' => $photo->id, 'color' => '#123456', 'mask_mode' => 'refined', 'base_mask_asset_id' => $selection['mask_asset_id'], 'preview' => true])->assertOk()->json('data.preview_id');
        $this->deleteJson($url.'/photo-simulations/'.$preview)->assertOk();
        $this->deleteJson($url.'/photo-simulations/'.$preview)->assertOk();
        $this->postJson($url.'/photo-simulations/'.$preview.'/confirm')->assertStatus(409);
        $this->getJson($url)->assertJsonCount(0, 'data.photo_editing.simulations');
        $this->assertDatabaseHas('property_processing_jobs', ['property_visualization_id' => $visual->id, 'kind' => 'photo_preview', 'status' => 'cancelled']);
        [$otherUrl] = $this->setupPhoto('other');
        $this->postJson($url.'/photo-simulations/'.$preview.'/confirm')->assertNotFound();
        $this->deleteJson($otherUrl.'/photo-simulations/'.$preview)->assertNotFound();
    }

    public function test_worker_failure_records_failed_job_and_releases_processing_guard(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo] = $this->setupPhoto();
        Http::fake(['*' => Http::response(['detail' => 'failure'], 503)]);
        $this->postJson($url.'/photo-selections', ['asset_id' => $photo->id, 'points' => [['x' => 1, 'y' => 1, 'label' => 1]]])->assertServerError();
        $this->assertDatabaseHas('property_processing_jobs', ['property_visualization_id' => $visual->id, 'status' => 'failed']);
        Http::swap(new Factory);
        $this->fakeWorker();
        $this->select($url, $photo->id);
    }

    public function test_negative_only_and_duplicate_processing_are_rejected_without_worker_access(): void
    {
        Storage::fake('local');
        [$url, $visual, $photo, $user] = $this->setupPhoto();
        Http::preventStrayRequests();
        $this->postJson($url.'/photo-selections', ['asset_id' => $photo->id, 'points' => [['x' => 1, 'y' => 1, 'label' => 0]]])->assertUnprocessable();
        $visual->processingJobs()->create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'kind' => 'photo_selection', 'status' => 'processing', 'input_asset_ids' => [$photo->id], 'started_at' => now()]);
        $this->postJson($url.'/photo-selections', ['asset_id' => $photo->id, 'points' => [['x' => 1, 'y' => 1, 'label' => 1]]])->assertStatus(409);
        Http::assertNothingSent();
    }
}
