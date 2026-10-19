<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityPhoto;
use App\Models\OpportunityStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_url_requires_safe_public_https_and_legacy_values_are_filtered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $payload = ['title' => 'Origem externa', 'property_type' => 'house', 'source' => 'manual'];

        foreach ([
            'http://portal.example.test/item',
            'https://user:secret@portal.example.test/item',
            'https://localhost/item',
            'https://127.0.0.1/item',
            'https://2130706433/item',
            'https://10.0.0.5/item',
            'https://portal.local/item',
            'https://portal.example.test:8443/item',
        ] as $unsafeUrl) {
            $this->postJson('/api/opportunities', [...$payload, 'source_url' => $unsafeUrl])
                ->assertUnprocessable()->assertJsonValidationErrors('source_url');
        }

        $this->postJson('/api/opportunities', [...$payload, 'source_url' => 'https://portal.example.test:443/item'])
            ->assertCreated()->assertJsonPath('data.source_url', 'https://portal.example.test:443/item');

        $legacy = Opportunity::create([...$payload, 'user_id' => $user->id, 'source_url' => 'https://127.0.0.1/private']);
        $response = $this->getJson("/api/opportunities/{$legacy->id}")
            ->assertOk()->assertJsonPath('data.source_url', null);
        $this->assertStringNotContainsString('https://127.0.0.1/private', $response->getContent());
    }

    public function test_external_cover_requires_a_safe_public_https_url(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $payload = ['title' => 'Capa externa', 'property_type' => 'house', 'source' => 'manual'];

        foreach ([
            'http://images.example.test/cover.jpg',
            'https://user:secret@images.example.test/cover.jpg',
            'https://localhost/cover.jpg',
            'https://127.0.0.1/cover.jpg',
            'https://10.0.0.5/cover.jpg',
            'https://images.local/cover.jpg',
            'https://images.example.test:8443/cover.jpg',
        ] as $unsafeUrl) {
            $this->postJson('/api/opportunities', [...$payload, 'cover_image_url' => $unsafeUrl])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('cover_image_url');
        }

        $this->postJson('/api/opportunities', [
            ...$payload,
            'cover_image_url' => 'https://images.example.test:443/cover.jpg?size=large',
        ])->assertCreated()->assertJsonPath('data.cover_url', 'https://images.example.test:443/cover.jpg?size=large');

        $this->assertDatabaseCount('opportunities', 1);
        $this->assertDatabaseCount('opportunity_photos', 1);
    }

    public function test_legacy_unsafe_external_photos_are_not_returned_to_the_browser(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'user_id' => $user->id,
            'title' => 'Capa legada',
            'property_type' => 'house',
            'source' => 'manual',
        ]);
        $unsafe = $opportunity->photos()->create([
            'path' => 'http://127.0.0.1/private-status',
            'original_name' => 'unsafe.jpg',
            'is_cover' => true,
        ]);
        $safe = $opportunity->photos()->create([
            'path' => 'https://images.example.test/safe.jpg',
            'original_name' => 'safe.jpg',
            'is_cover' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_url', null)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.photos.0.id', $safe->id)
            ->assertJsonMissing(['url' => $unsafe->path]);

        $this->assertStringNotContainsString($unsafe->path, $response->getContent());
    }

    public function test_unmanaged_local_photo_paths_are_not_returned_to_the_browser(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'user_id' => $user->id,
            'title' => 'Caminhos legados',
            'property_type' => 'house',
            'source' => 'manual',
        ]);
        $traversal = $opportunity->photos()->create([
            'path' => 'opportunities/../documents/private.pdf',
            'original_name' => 'unsafe.jpg',
            'is_cover' => true,
        ]);
        $foreignNamespace = $opportunity->photos()->create([
            'path' => 'documents/tenant/private.jpg',
            'original_name' => 'foreign.jpg',
        ]);
        $safe = $opportunity->photos()->create([
            'path' => 'opportunities/tenant/safe-photo.jpg',
            'original_name' => 'safe-photo.jpg',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_url', null)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.photos.0.id', $safe->id)
            ->assertJsonPath('data.photos.0.url', '/storage/opportunities/tenant/safe-photo.jpg');

        $this->assertStringNotContainsString($traversal->path, $response->getContent());
        $this->assertStringNotContainsString($foreignNamespace->path, $response->getContent());
    }

    public function test_photo_limit_applies_to_existing_and_new_photos_combined(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual']);
        for ($i = 0; $i < 20; $i++) {
            $opportunity->photos()->create(['path' => "audit/$i.jpg", 'original_name' => "$i.jpg", 'order' => $i, 'is_cover' => $i === 0]);
        }
        $this->post("/api/opportunities/{$opportunity->id}", ['_method' => 'PUT', 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual', 'photos' => [UploadedFile::fake()->image('extra.jpg')]], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('opportunity_photos', 20);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $opportunity->photos()->firstOrFail()->delete();
        $this->post("/api/opportunities/{$opportunity->id}", ['_method' => 'PUT', 'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual', 'photos' => [UploadedFile::fake()->image('allowed.jpg')], 'cover_photo' => 0], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('opportunity_photos', 20);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_cover_index_must_reference_one_of_the_uploaded_photos(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(), 'sanctum')->post('/api/opportunities', ['title' => 'Audit', 'property_type' => 'house', 'source' => 'manual', 'photos' => [UploadedFile::fake()->image('audit.jpg')], 'cover_photo' => 99], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photo_dimensions_and_original_filename_are_bounded(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->post('/api/opportunities', [
            'title' => 'Imagem excessiva', 'property_type' => 'house', 'source' => 'manual',
            'photos' => [UploadedFile::fake()->image('larga.jpg', 12001, 1)],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $response = $this->post('/api/opportunities', [
            'title' => 'Nome seguro', 'property_type' => 'house', 'source' => 'manual',
            'photos' => [UploadedFile::fake()->image("../../PRIVATE\nphoto.jpg", 10, 10)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $name = $response->json('data.photos.0.name');
        $this->assertSame('PRIVATE_photo.jpg', $name);
        $this->assertStringNotContainsString('..', $name);
        $this->assertStringNotContainsString("\n", $name);
        $this->assertSame($name, OpportunityPhoto::firstOrFail()->original_name);
    }

    public function test_failed_opportunity_creation_removes_new_photo_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = 'eloquent.creating: '.OpportunityStatusHistory::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated history failure'));
        try {
            $this->actingAs($user, 'sanctum')->post('/api/opportunities', [
                'title' => 'Audit', 'property_type' => 'house', 'source' => 'manual',
                'photos' => [UploadedFile::fake()->image('audit.jpg')],
            ], ['Accept' => 'application/json'])->assertStatus(500);
            $this->assertDatabaseCount('opportunities', 0);
            $this->assertDatabaseCount('opportunity_photos', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            app('events')->forget($event);
        }
    }

    public function test_failed_opportunity_update_preserves_existing_photos_and_removes_only_new_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $id = $this->post('/api/opportunities', ['title' => 'Original', 'property_type' => 'house', 'source' => 'manual', 'photos' => [UploadedFile::fake()->image('original.jpg')]], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $beforeFiles = Storage::disk('public')->allFiles();
        $event = 'eloquent.creating: '.OpportunityStatusHistory::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('Audit simulated history failure'));
        try {
            $this->post("/api/opportunities/$id", ['_method' => 'PUT', 'title' => 'Changed', 'property_type' => 'house', 'source' => 'manual', 'status' => 'analyzing', 'photos' => [UploadedFile::fake()->image('new.jpg')]], ['Accept' => 'application/json'])->assertStatus(500);
            $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
            $this->assertDatabaseHas('opportunities', ['id' => $id, 'title' => 'Original', 'status' => 'new']);
            $this->assertDatabaseCount('opportunity_photos', 1);
        } finally {
            app('events')->forget($event);
        }
    }

    public function test_user_can_create_list_and_view_opportunity_without_photos(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $id = $this->postJson('/api/opportunities', ['title' => 'Sem fotos', 'property_type' => 'house', 'source' => 'manual'])
            ->assertCreated()->assertJsonPath('data.cover_url', null)->json('data.id');
        $this->getJson('/api/opportunities')->assertOk()->assertJsonPath('data.0.cover_url', null);
        $this->getJson("/api/opportunities/$id")->assertOk()->assertJsonPath('data.cover_url', null);
    }

    public function test_opportunity_patch_is_partial_and_put_remains_complete(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'user_id' => $user->id,
            'title' => 'Oportunidade original',
            'property_type' => 'house',
            'source' => 'manual',
            'asking_price' => 300000,
            'city' => 'São Paulo',
            'notes' => 'Nota preservada',
        ]);
        $photo = $opportunity->photos()->create([
            'path' => 'https://images.example.test/original.jpg',
            'original_name' => 'original.jpg',
            'is_cover' => true,
        ]);
        $this->actingAs($user, 'sanctum');

        $this->patchJson("/api/opportunities/{$opportunity->id}", ['asking_price' => 325000])
            ->assertOk()
            ->assertJsonPath('data.asking_price', '325000.00')
            ->assertJsonPath('data.title', 'Oportunidade original');

        $opportunity->refresh();
        $this->assertSame('house', $opportunity->property_type->value);
        $this->assertSame('manual', $opportunity->source->value);
        $this->assertSame('São Paulo', $opportunity->city);
        $this->assertSame('Nota preservada', $opportunity->notes);
        $this->assertDatabaseHas('opportunity_photos', ['id' => $photo->id, 'is_cover' => true]);
        $this->assertDatabaseCount('opportunity_photos', 1);

        $this->putJson("/api/opportunities/{$opportunity->id}", ['asking_price' => 350000])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'property_type', 'source']);
    }

    public function test_archive_status_is_consistent_when_created_and_edited(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $payload = ['title' => 'Audit archived', 'property_type' => 'house', 'source' => 'manual', 'status' => 'archived'];
        $id = $this->postJson('/api/opportunities', $payload)->assertCreated()->assertJsonPath('data.is_archived', true)->json('data.id');
        $this->getJson('/api/opportunities')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/opportunities/$id", array_replace($payload, ['status' => 'new']))->assertOk()->assertJsonPath('data.is_archived', false);
        $this->getJson('/api/opportunities')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/opportunities/$id", $payload)->assertOk()->assertJsonPath('data.is_archived', true);
        $this->getJson('/api/opportunities')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_multipart_update_can_clear_optional_fields(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $payload = ['title' => 'Audit', 'property_type' => 'house', 'source' => 'manual', 'city' => 'Teste', 'asking_price' => 100, 'notes' => 'Teste'];
        $id = $this->postJson('/api/opportunities', $payload)->assertCreated()->json('data.id');
        $this->post("/api/opportunities/$id", array_replace($payload, ['_method' => 'PUT', 'city' => '', 'asking_price' => '', 'notes' => '']), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.city', null)->assertJsonPath('data.asking_price', null)->assertJsonPath('data.notes', null);
        $this->assertDatabaseHas('opportunities', ['id' => $id, 'city' => null, 'asking_price' => null, 'notes' => null]);
    }

    public function test_user_can_create_an_opportunity_with_photo_and_initial_history(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->post(route('api.opportunities.store'), [
            'title' => 'Casa Jardim das Flores',
            'property_type' => 'house',
            'source' => 'manual',
            'asking_price' => 285000,
            'photos' => [UploadedFile::fake()->image('casa.jpg')],
        ]);

        $response->assertCreated();
        $opportunity = Opportunity::firstOrFail();
        $this->assertSame($user->id, $opportunity->user_id);
        $this->assertDatabaseHas('opportunity_status_histories', ['opportunity_id' => $opportunity->id, 'old_status' => null, 'new_status' => 'new']);
        Storage::disk('public')->assertExists($opportunity->photos()->firstOrFail()->path);
    }

    public function test_deleting_opportunity_removes_local_public_photos_and_photo_metadata(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Excluir fotos', 'property_type' => 'house', 'source' => 'manual']);
        $local = $opportunity->photos()->create(['path' => 'opportunities/local.jpg', 'original_name' => 'local.jpg', 'is_cover' => true]);
        $opportunity->photos()->create(['path' => 'https://images.example.test/external.jpg', 'original_name' => 'external.jpg']);
        Storage::disk('public')->put($local->path, 'photo-bytes');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/opportunities/{$opportunity->id}")->assertOk();

        Storage::disk('public')->assertMissing($local->path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('opportunity_photos', ['opportunity_id' => $opportunity->id]);
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_deleting_opportunity_rejects_unmanaged_local_photo_paths_without_changing_data(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Caminho inválido', 'property_type' => 'house', 'source' => 'manual']);
        $photo = $opportunity->photos()->create(['path' => 'unexpected/local.jpg', 'original_name' => 'local.jpg', 'is_cover' => true]);
        Storage::disk('public')->put($photo->path, 'photo-bytes');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/opportunities/{$opportunity->id}")
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Não foi possível remover as fotos públicas. Tente novamente.']);

        Storage::disk('public')->assertExists($photo->path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('opportunity_photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);
    }

    public function test_failed_opportunity_delete_restores_quarantined_photo_and_database_records(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Rollback', 'property_type' => 'house', 'source' => 'manual']);
        $photo = $opportunity->photos()->create(['path' => 'opportunities/rollback.jpg', 'original_name' => 'rollback.jpg', 'is_cover' => true]);
        Storage::disk('public')->put($photo->path, 'photo-bytes');
        $event = 'eloquent.deleting: '.Opportunity::class;
        app('events')->listen($event, fn () => throw new \RuntimeException('Simulated database failure'));

        try {
            $this->actingAs($user, 'sanctum')->deleteJson("/api/opportunities/{$opportunity->id}")->assertStatus(500);

            Storage::disk('public')->assertExists($photo->path);
            $this->assertSame([], Storage::disk('local')->allFiles());
            $this->assertDatabaseHas('opportunity_photos', ['id' => $photo->id]);
            $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);
        } finally {
            app('events')->forget($event);
        }
    }

    public function test_user_cannot_view_or_change_another_users_opportunity(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $owner->id, 'title' => 'Privada', 'property_type' => 'house', 'source' => 'manual']);

        $this->actingAs($intruder, 'sanctum')->get(route('api.opportunities.show', $opportunity))->assertNotFound();
        $this->actingAs($intruder, 'sanctum')->patch("/api/opportunities/{$opportunity->id}/favorite")->assertNotFound();
    }

    public function test_listing_tolerates_legacy_opportunity_without_timestamps(): void
    {
        $user = User::factory()->create();
        $id = \DB::table('opportunities')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Legado sem datas',
            'property_type' => 'house',
            'source' => 'manual',
            'status' => 'new',
            'is_favorite' => false,
            'is_archived' => false,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/opportunities')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.created_at', null)
            ->assertJsonPath('data.0.updated_at', null);
    }

    public function test_discard_requires_reason_and_records_status_history(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Em avaliação', 'property_type' => 'apartment', 'source' => 'olx']);

        $this->actingAs($user, 'sanctum')->patchJson("/api/opportunities/{$opportunity->id}/discard")->assertUnprocessable()->assertJsonValidationErrors('discarded_reason');
        $this->actingAs($user, 'sanctum')->patchJson("/api/opportunities/{$opportunity->id}/discard", ['discarded_reason' => 'Margem insuficiente'])->assertOk();

        $opportunity->refresh();
        $this->assertSame(OpportunityStatus::Discarded, $opportunity->status);
        $this->assertSame('Margem insuficiente', $opportunity->discarded_reason);
        $this->assertDatabaseHas('opportunity_status_histories', ['opportunity_id' => $opportunity->id, 'new_status' => 'discarded']);
    }

    public function test_user_can_select_an_existing_photo_as_cover(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Com galeria', 'property_type' => 'house', 'source' => 'manual']);
        $first = OpportunityPhoto::create(['opportunity_id' => $opportunity->id, 'path' => 'opportunities/1.jpg', 'original_name' => '1.jpg', 'is_cover' => true]);
        $second = OpportunityPhoto::create(['opportunity_id' => $opportunity->id, 'path' => 'opportunities/2.jpg', 'original_name' => '2.jpg', 'order' => 1]);

        $this->actingAs($user, 'sanctum')->putJson(route('api.opportunities.update', $opportunity), [
            'title' => $opportunity->title, 'property_type' => 'house', 'source' => 'manual', 'existing_cover_photo_id' => $second->id,
        ])->assertOk();

        $this->assertFalse($first->refresh()->is_cover);
        $this->assertTrue($second->refresh()->is_cover);
    }

    public function test_photo_from_another_opportunity_cannot_be_selected_as_cover(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $user->id, 'title' => 'Primeira', 'property_type' => 'house', 'source' => 'manual']);
        $other = Opportunity::create(['user_id' => $user->id, 'title' => 'Segunda', 'property_type' => 'house', 'source' => 'manual']);
        $foreignPhoto = OpportunityPhoto::create(['opportunity_id' => $other->id, 'path' => 'opportunities/x.jpg', 'original_name' => 'x.jpg']);

        $this->actingAs($user, 'sanctum')->putJson(route('api.opportunities.update', $opportunity), [
            'title' => $opportunity->title, 'property_type' => 'house', 'source' => 'manual', 'existing_cover_photo_id' => $foreignPhoto->id,
        ])->assertNotFound();
    }
}
