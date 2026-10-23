<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PlatformWorkflowController;
use App\Jobs\PlatformExportReport;
use App\Models\PlatformAdmin;
use App\Models\PlatformEvent;
use App\Models\PlatformMfaCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\PlatformQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/workflow-test';

    protected function setUp(): void
    {
        parent::setUp();
        // Temporary contract routes exercise real auth/admin/MFA middleware;
        // the coordinator independently wires the production route group.
        Route::middleware(['auth:sanctum', 'active-account', 'verified-account', 'platform-admin'])->prefix('api/workflow-test')->group(function () {
            Route::get('exports', [PlatformWorkflowController::class, 'exports']);
            Route::post('exports', [PlatformWorkflowController::class, 'storeExport']);
            Route::get('exports/{id}/download', [PlatformWorkflowController::class, 'downloadExport']);
            Route::post('exports/{id}/retry', [PlatformWorkflowController::class, 'retryExport']);
            Route::post('exports/{id}/cancel', [PlatformWorkflowController::class, 'cancelExport']);
            Route::get('privacy', [PlatformWorkflowController::class, 'privacy']);
            Route::post('privacy', [PlatformWorkflowController::class, 'storePrivacy']);
            Route::patch('privacy/{id}', [PlatformWorkflowController::class, 'updatePrivacy']);
            Route::put('signals/{id}/review', [PlatformWorkflowController::class, 'reviewSignal']);
            Route::get('administrators', [PlatformWorkflowController::class, 'administrators']);
            Route::post('administrators/action', [PlatformWorkflowController::class, 'adminAction']);
        });
        Bus::fake([PlatformExportReport::class]);
        Storage::fake('local');
    }

    private function operator(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        PlatformAdmin::create(['user_id' => $user->id, 'email' => $user->email]);
        $this->platformSession($user);

        return $user;
    }

    private function asynchronous(): void
    {
        config(['queue.default' => 'database', 'queue.connections.platform_ops' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'platform-ops', 'retry_after' => 180, 'after_commit' => true]]);
    }

    private function confirmation(): array
    {
        return ['password' => 'password', 'confirmed' => true, 'reason' => 'qa_validation'];
    }

    public function test_common_users_and_unverified_mfa_cannot_operate_workflows(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::BASE.'/administrators')->assertForbidden();
        $this->postJson(self::BASE.'/exports', ['report' => 'clients'])->assertForbidden();
        $this->postJson(self::BASE.'/privacy', ['user_id' => 1, 'type' => 'deletion'])->assertForbidden();
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'grant', 'user_id' => 1] + $this->confirmation())->assertForbidden();
        $admin = $this->operator();
        $admin->currentAccessToken()->forceFill(['platform_mfa_at' => null])->save();
        $this->getJson(self::BASE.'/exports')->assertForbidden()->assertJsonPath('code', 'platform_mfa_required');
    }

    public function test_export_requires_async_queue_and_validated_filters(): void
    {
        $this->operator();
        $this->postJson(self::BASE.'/exports', ['report' => 'clients'])->assertStatus(503);
        $this->assertDatabaseCount('platform_export_jobs', 0);
        $this->asynchronous();
        $this->postJson(self::BASE.'/exports', ['report' => 'private_messages'])->assertUnprocessable();
        $this->postJson(self::BASE.'/exports', ['report' => 'clients', 'from' => '2000-01-01'])->assertUnprocessable();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::BASE.'/exports', ['report' => 'clients'])->assertStatus(202);
        }
        $this->postJson(self::BASE.'/exports', ['report' => 'clients'])->assertStatus(429);
        Bus::assertDispatchedTimes(PlatformExportReport::class, 3);
    }

    public function test_export_worker_is_idempotent_private_sanitized_and_owner_only(): void
    {
        $owner = $this->operator();
        $this->asynchronous();
        $user = User::factory()->create(['name' => '=HYPERLINK("unsafe")']);
        $id = $this->postJson(self::BASE.'/exports', ['report' => 'clients', 'user_id' => $user->id])->assertStatus(202)->json('data.id');
        $job = new PlatformExportReport($id);
        $job->handle(app(PlatformQueries::class));
        $job->handle(app(PlatformQueries::class));
        $this->assertDatabaseHas('platform_export_jobs', ['id' => $id, 'status' => 'completed', 'rows' => 1, 'attempts' => 1]);
        $csv = Storage::disk('local')->get(PlatformExportReport::path($id));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString($user->email, $csv);
        $this->assertStringNotContainsString('password', $csv);
        $this->get(self::BASE.'/exports/'.$id.'/download')->assertOk();
        $this->operator();
        $this->getJson(self::BASE.'/exports')->assertOk()->assertJsonCount(0, 'data');
        foreach (['download' => 'getJson', 'retry' => 'postJson', 'cancel' => 'postJson'] as $action => $verb) {
            $this->{$verb}(self::BASE.'/exports/'.$id.'/'.$action)->assertNotFound();
        }
        $this->platformSession($owner);
        DB::table('platform_export_jobs')->where('id', $id)->update(['expires_at' => now()->subSecond()]);
        $this->getJson(self::BASE.'/exports/'.$id.'/download')->assertStatus(410);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.export.completed']);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.export.downloaded']);
    }

    public function test_cancel_retry_and_old_attempt_failure_preserve_new_generation(): void
    {
        $this->operator();
        $this->asynchronous();
        $id = $this->postJson(self::BASE.'/exports', ['report' => 'events'])->assertStatus(202)->json('data.id');
        $old = new PlatformExportReport($id);
        $this->postJson(self::BASE.'/exports/'.$id.'/cancel')->assertOk();
        $old->handle(app(PlatformQueries::class));
        $this->assertDatabaseHas('platform_export_jobs', ['id' => $id, 'status' => 'cancelled', 'attempts' => 0]);
        $this->postJson(self::BASE.'/exports/'.$id.'/retry')->assertStatus(202);
        DB::table('platform_export_jobs')->where('id', $id)->update(['attempts' => 1, 'status' => 'failed']);
        $this->postJson(self::BASE.'/exports/'.$id.'/retry')->assertStatus(202);
        $new = new PlatformExportReport($id);
        DB::table('platform_export_jobs')->where('id', $id)->update(['attempts' => 2, 'status' => 'processing']);
        $old->failed(new \RuntimeException('secret path not for audit'));
        $this->assertDatabaseHas('platform_export_jobs', ['id' => $id, 'status' => 'processing', 'attempts' => 2]);
        $new->failed(null);
        $this->assertDatabaseHas('platform_export_jobs', ['id' => $id, 'status' => 'failed', 'error_type' => 'WorkerInterrupted']);
        DB::table('platform_export_jobs')->where('id', $id)->update(['attempts' => 3]);
        $this->postJson(self::BASE.'/exports/'.$id.'/retry')->assertStatus(409);
    }

    public function test_privacy_workflow_derives_tenant_and_never_deletes_user(): void
    {
        $admin = $this->operator();
        $tenant = Tenant::create(['nome' => 'Privacy QA', 'slug' => 'privacy-qa']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $id = $this->postJson(self::BASE.'/privacy', ['user_id' => $user->id, 'tenant_id' => 99999, 'type' => 'deletion', 'notes' => 'Pedido autorizado para revisão humana.'])->assertCreated()->assertJsonPath('data.tenant_id', $tenant->id)->json('data.id');
        $this->patchJson(self::BASE.'/privacy/'.$id, ['status' => 'completed', 'notes' => 'Attempt skip'])->assertUnprocessable();
        $this->patchJson(self::BASE.'/privacy/'.$id, ['status' => 'reviewing', 'assigned_user_id' => $user->id])->assertUnprocessable();
        $this->patchJson(self::BASE.'/privacy/'.$id, ['status' => 'reviewing', 'assigned_user_id' => $admin->id])->assertOk();
        $this->patchJson(self::BASE.'/privacy/'.$id, ['status' => 'approved'])->assertOk();
        $this->patchJson(self::BASE.'/privacy/'.$id, ['status' => 'completed', 'notes' => 'Encaminhamento manual concluído no ambiente de QA.'])->assertOk();
        $this->assertNotNull($user->fresh());
        $this->getJson(self::BASE.'/privacy?tenant_id='.$tenant->id)->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.privacy.updated']);
    }

    public function test_signal_review_requires_security_event_and_is_audited_without_training(): void
    {
        $this->operator();
        $security = PlatformEvent::create(['category' => 'security', 'event' => 'security.signal.detected']);
        $other = PlatformEvent::create(['category' => 'api', 'event' => 'request.completed']);
        $this->putJson(self::BASE.'/signals/'.$other->id.'/review', ['classification' => 'confirmed'])->assertNotFound();
        $this->putJson(self::BASE.'/signals/'.$security->id.'/review', ['classification' => 'arbitrary'])->assertUnprocessable();
        foreach (['probable', 'false_positive', 'expected', 'confirmed'] as $value) {
            $this->putJson(self::BASE.'/signals/'.$security->id.'/review', ['classification' => $value])->assertOk();
        }
        $this->assertDatabaseCount('platform_signal_reviews', 1);
        $this->assertDatabaseHas('platform_signal_reviews', ['event_id' => $security->id, 'classification' => 'confirmed']);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.signal.reviewed']);
    }

    public function test_admin_registry_requires_recent_mfa_and_protects_self_configuration_and_accounts(): void
    {
        $actor = $this->operator();
        $target = User::factory()->create(['email_verified_at' => now()]);
        $payload = ['action' => 'grant', 'user_id' => $target->id] + $this->confirmation();
        $this->postJson(self::BASE.'/administrators/action', array_replace($payload, ['password' => 'wrong']))->assertForbidden();
        $actor->currentAccessToken()->forceFill(['platform_recent_auth_at' => now()->subMinutes(11)])->save();
        $this->postJson(self::BASE.'/administrators/action', $payload)->assertForbidden();
        $actor->currentAccessToken()->forceFill(['platform_recent_auth_at' => now()])->save();
        $this->postJson(self::BASE.'/administrators/action', $payload)->assertOk();
        $this->assertTrue($target->fresh()->isPlatformAdmin());
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'remove', 'user_id' => $actor->id] + $this->confirmation())->assertUnprocessable();
        config(['platform.admin_emails' => [mb_strtolower($target->email)]]);
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'remove', 'user_id' => $target->id] + $this->confirmation())->assertUnprocessable();
        $invalid = User::factory()->create(['email_verified_at' => null]);
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'grant', 'user_id' => $invalid->id] + $this->confirmation())->assertUnprocessable();
        $this->getJson(self::BASE.'/administrators')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_reset_and_remove_revoke_credentials_and_sessions(): void
    {
        $target = $this->operator();
        $actor = $this->operator();
        $this->assertTrue(PlatformMfaCredential::where('user_id', $target->id)->exists());
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'reset_mfa', 'user_id' => $target->id] + $this->confirmation())->assertOk();
        $this->assertFalse(PlatformMfaCredential::where('user_id', $target->id)->exists());
        $this->assertSame(0, $target->tokens()->count());
        $this->assertTrue($target->fresh()->isPlatformAdmin());
        $this->postJson(self::BASE.'/administrators/action', ['action' => 'remove', 'user_id' => $target->id] + $this->confirmation())->assertOk();
        $this->assertFalse($target->fresh()->isPlatformAdmin());
        $this->assertTrue($actor->fresh()->isPlatformAdmin());
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.registry.reset_mfa']);
        $this->assertDatabaseHas('platform_events', ['event' => 'admin.registry.remove']);
    }
}
