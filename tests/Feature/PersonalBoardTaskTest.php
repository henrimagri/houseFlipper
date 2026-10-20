<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\PersonalBoardTask;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalBoardTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_broker_manages_personal_tasks_without_creating_crm_contacts(): void
    {
        [$user, $token] = $this->broker('organizer');

        $created = $this->withToken($token)->postJson('/api/personal-board/tasks', [
            'title' => 'Preparar anúncio',
            'description' => 'Revisar as fotos',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => '2026-10-25',
        ])->assertCreated()->json('data');

        $this->assertDatabaseCount('crm_contacts', 0);
        $this->withToken($token)->patchJson('/api/personal-board/tasks/'.$created['id'], ['status' => 'doing'])
            ->assertOk()->assertJsonPath('data.status', 'doing');
        $this->withToken($token)->getJson('/api/personal-board/tasks')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->deleteJson('/api/personal-board/tasks/'.$created['id'])->assertOk();
        $this->assertDatabaseCount('personal_board_tasks', 0);
    }

    public function test_broker_cannot_change_another_users_personal_task(): void
    {
        [$owner] = $this->broker('owner-board');
        [, $foreignToken] = $this->broker('foreign-board');
        $task = PersonalBoardTask::query()->withoutGlobalScopes()->create([
            'tenant_id' => $owner->tenant_id, 'user_id' => $owner->id, 'title' => 'Privada', 'status' => 'backlog', 'priority' => 'normal',
        ]);

        $this->withToken($foreignToken)->patchJson('/api/personal-board/tasks/'.$task->id, ['status' => 'done'])->assertNotFound();
    }

    private function broker(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => 'corretor', 'active' => true, 'status' => SubscriptionStatus::Active]);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
