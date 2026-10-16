<?php

namespace Tests\Feature;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_cannot_view_or_change_another_users_opportunity(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $opportunity = Opportunity::create(['user_id' => $owner->id, 'title' => 'Privada', 'property_type' => 'house', 'source' => 'manual']);

        $this->actingAs($intruder, 'sanctum')->get(route('api.opportunities.show', $opportunity))->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->patch("/api/opportunities/{$opportunity->id}/favorite")->assertForbidden();
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
