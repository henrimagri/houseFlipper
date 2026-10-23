<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\CommercializationAuthorization;
use App\Models\CommercialListing;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmTicket;
use App\Models\OrganizationMembership;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Workspace\AgencyCrmService;
use App\Services\Workspace\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgencyInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_minimal_ticket_uses_normal_priority(): void
    {
        [, $token] = $this->agency('minimal');
        $this->withToken($token)->postJson('/api/imobiliaria/tickets', ['subject' => 'Visita'])
            ->assertCreated()->assertJsonPath('data.priority', 'normal')->assertJsonPath('data.channel', 'internal');
    }

    public function test_repeated_conversion_returns_same_deal_and_preserves_ticket_context(): void
    {
        [$user, $token] = $this->agency('repeat');
        $ticket = app(AgencyCrmService::class)->createTicket($user, ['subject' => 'Agendar visita', 'priority' => 'alta']);
        $first = $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/convert')->assertOk()->json('data.id');
        $second = $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/convert')->assertOk()->json('data.id');
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('crm_deals', 1);
        $this->assertDatabaseCount('crm_deal_activities', 1);
        $this->assertDatabaseHas('crm_tickets', ['id' => $ticket->id, 'crm_deal_id' => $first]);
        $this->assertDatabaseHas('crm_deals', ['id' => $first, 'title' => 'Agendar visita', 'priority' => 'alta', 'assigned_user_id' => $user->id]);
    }

    public function test_conversion_reloads_stale_ticket_instead_of_duplicating(): void
    {
        [$user] = $this->agency('stale');
        $crm = app(AgencyCrmService::class);
        $ticket = $crm->createTicket($user, ['subject' => 'Visita', 'priority' => 'normal']);
        $stale = clone $ticket;
        $first = $crm->convertTicket($user, $ticket);
        $this->assertSame($first->id, $crm->convertTicket($user, $stale)->id);
        $this->assertDatabaseCount('crm_deals', 1);
    }

    public function test_link_failure_rolls_back_deal_and_history(): void
    {
        [$user] = $this->agency('rollback');
        $crm = app(AgencyCrmService::class);
        $ticket = $crm->createTicket($user, ['subject' => 'Visita', 'priority' => 'normal']);
        Event::listen('eloquent.updating: '.CrmTicket::class, function (CrmTicket $ticket): void {
            if ($ticket->isDirty('crm_deal_id')) {
                throw new \RuntimeException('Falha controlada ao persistir vínculo');
            }
        });
        try {
            $crm->convertTicket($user, $ticket);
            $this->fail('A falha de persistência precisa ser propagada.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha controlada ao persistir vínculo', $exception->getMessage());
        }
        $this->assertDatabaseCount('crm_deals', 0);
        $this->assertDatabaseCount('crm_deal_activities', 0);
        $this->assertNull($ticket->fresh()->crm_deal_id);
    }

    public function test_archived_link_is_not_silently_recreated(): void
    {
        [$user, $token] = $this->agency('archived');
        $crm = app(AgencyCrmService::class);
        $ticket = $crm->createTicket($user, ['subject' => 'Visita', 'priority' => 'normal']);
        $deal = $crm->convertTicket($user, $ticket);
        $crm->archiveDeal($user, $deal);
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/convert')->assertConflict();
        $this->assertSame(1, CrmDeal::withTrashed()->count());
        $this->assertDatabaseHas('crm_tickets', ['id' => $ticket->id, 'crm_deal_id' => $deal->id]);
    }

    public function test_ticket_from_another_tenant_cannot_be_converted(): void
    {
        [, $token] = $this->agency('local');
        [$foreign] = $this->agency('foreign');
        $ticket = app(AgencyCrmService::class)->createTicket($foreign, ['subject' => 'Privado', 'priority' => 'normal']);
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/convert')->assertNotFound();
        $this->assertDatabaseCount('crm_deals', 0);
    }

    public function test_manager_cannot_reference_a_foreign_branch(): void
    {
        [$user, $token] = $this->agency('branch-local');
        [$foreign] = $this->agency('branch-foreign');
        $branch = app(OrganizationService::class)->createBranch($foreign, ['name' => 'Privada']);
        foreach (['deals' => 'title', 'tickets' => 'subject'] as $route => $field) {
            $this->withToken($token)->postJson('/api/imobiliaria/'.$route, [$field => 'Inválido', 'branch_id' => $branch->id, 'priority' => 'normal'])
                ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        }
        $this->assertDatabaseCount('crm_deals', 0);
        $this->assertDatabaseCount('crm_tickets', 0);
    }

    public function test_branch_agent_defaults_to_own_branch_but_cannot_clear_it(): void
    {
        [$owner] = $this->agency('branch-agent');
        [$agent, $token, $branch] = $this->agent($owner);
        $id = $this->withToken($token)->postJson('/api/imobiliaria/deals', ['title' => 'Minha filial'])
            ->assertCreated()->assertJsonPath('data.branch_id', $branch->id)->json('data.id');
        $this->withToken($token)->patchJson('/api/imobiliaria/deals/'.$id, ['branch_id' => null])->assertForbidden();
        $this->assertDatabaseHas('crm_deals', ['id' => $id, 'branch_id' => $branch->id]);
        $this->withToken($token)->postJson('/api/imobiliaria/tickets', ['subject' => 'Filial', 'priority' => 'normal'])
            ->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_branch_agent_cannot_read_unassigned_or_other_branch_tickets(): void
    {
        [$owner] = $this->agency('branch-read');
        [, $token] = $this->agent($owner);
        $otherBranch = app(OrganizationService::class)->createBranch($owner, ['name' => 'Outra']);
        $crm = app(AgencyCrmService::class);
        foreach ([null, $otherBranch->id] as $branch) {
            $ticket = $crm->createTicket($owner, ['subject' => 'Restrito', 'branch_id' => $branch, 'priority' => 'normal']);
            app('auth')->forgetGuards();
            $this->withToken($token)->getJson('/api/imobiliaria/tickets/'.$ticket->id)->assertForbidden();
            app('auth')->forgetGuards();
        }
        $this->withToken($token)->getJson('/api/imobiliaria/tickets')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_internal_note_on_whatsapp_ticket_is_not_an_external_delivery(): void
    {
        [$user, $token] = $this->agency('note');
        $ticket = app(AgencyCrmService::class)->createTicket($user, ['subject' => 'Retorno', 'priority' => 'normal', 'channel' => 'whatsapp']);
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/messages', ['body' => 'Observação da equipe', 'kind' => 'note'])
            ->assertOk()->assertJsonPath('data.messages.0.channel', 'internal')->assertJsonPath('data.messages.0.delivery.status', 'recorded');
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/messages', ['body' => 'Mensagem sem provedor'])
            ->assertOk()->assertJsonPath('data.messages.1.delivery.status', 'not_wired');
    }

    public function test_uninvited_user_cannot_become_owner_by_opening_organization(): void
    {
        [$owner] = $this->agency('uninvited');
        $user = User::query()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Sem convite', 'email' => 'uninvited-member@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        $this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/imobiliaria/organization')->assertForbidden();
        $this->assertDatabaseMissing('organization_memberships', ['user_id' => $user->id]);
    }

    public function test_inactive_member_cannot_read_organization_and_is_not_reactivated(): void
    {
        [$owner] = $this->agency('inactive');
        [$agent, $token] = $this->agent($owner);
        OrganizationMembership::query()->where('user_id', $agent->id)->update(['status' => 'inactive']);
        $this->withToken($token)->getJson('/api/imobiliaria/organization')->assertForbidden();
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $agent->id, 'status' => 'inactive', 'role' => 'agent']);
    }

    public function test_inviting_member_without_role_defaults_to_active_agent(): void
    {
        [$owner, $token] = $this->agency('invite');
        $user = User::query()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Convidado', 'email' => 'invited@example.test', 'password' => 'password']);
        $this->withToken($token)->postJson('/api/imobiliaria/members', ['user_id' => $user->id])
            ->assertOk()->assertJsonPath('data.role', 'agent')->assertJsonPath('data.status', 'active');
    }

    public function test_agent_cannot_link_contact_or_listing_from_other_branch(): void
    {
        [$owner] = $this->agency('references');
        [, $token] = $this->agent($owner);
        $other = app(OrganizationService::class)->createBranch($owner, ['name' => 'Outra']);
        $contact = CrmContact::query()->create(['tenant_id' => $owner->tenant_id, 'organization_id' => $other->organization_id, 'branch_id' => $other->id, 'user_id' => $owner->id, 'name' => 'Restrito']);
        $listing = $this->listing($owner, ['organization_id' => $other->organization_id, 'branch_id' => $other->id]);
        foreach (['crm_contact_id' => $contact->id, 'commercial_listing_id' => $listing->id] as $field => $id) {
            $this->withToken($token)->postJson('/api/imobiliaria/tickets', ['subject' => 'Referência', 'priority' => 'normal', $field => $id])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('crm_tickets', 0);
    }

    public function test_archived_listing_cannot_be_attached_to_new_ticket(): void
    {
        [$owner, $token] = $this->agency('listing-archive');
        $listing = $this->listing($owner);
        $listing->delete();
        $this->withToken($token)->postJson('/api/imobiliaria/tickets', ['subject' => 'Referência', 'priority' => 'normal', 'commercial_listing_id' => $listing->id])
            ->assertUnprocessable()->assertJsonValidationErrors('commercial_listing_id');
    }

    public function test_foreign_link_cannot_return_another_tenants_deal_or_create_a_replacement(): void
    {
        [$user, $token] = $this->agency('link-local');
        [$foreign] = $this->agency('link-foreign');
        $crm = app(AgencyCrmService::class);
        $ticket = $crm->createTicket($user, ['subject' => 'Visita', 'priority' => 'normal']);
        $foreignDeal = $crm->createDeal($foreign, ['title' => 'Privado']);
        $ticket->forceFill(['crm_deal_id' => $foreignDeal->id])->save();
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$ticket->id.'/convert')->assertConflict();
        $this->assertDatabaseCount('crm_deals', 1);
        $this->assertDatabaseHas('crm_tickets', ['id' => $ticket->id, 'crm_deal_id' => $foreignDeal->id]);
    }

    public function test_invite_update_does_not_reset_existing_role_status_or_branch(): void
    {
        [$owner, $token] = $this->agency('invite-update');
        [$agent, , $branch] = $this->agent($owner);
        OrganizationMembership::query()->where('user_id', $agent->id)->update(['role' => 'manager', 'status' => 'inactive']);
        $this->withToken($token)->postJson('/api/imobiliaria/members', ['user_id' => $agent->id, 'specialties' => ['locacao']])
            ->assertOk()->assertJsonPath('data.role', 'manager')->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_authorized_branch_references_survive_conversion(): void
    {
        [$owner] = $this->agency('valid-references');
        [$agent, $token, $branch] = $this->agent($owner);
        $contact = CrmContact::query()->create(['tenant_id' => $owner->tenant_id, 'organization_id' => $branch->organization_id, 'branch_id' => $branch->id, 'user_id' => $owner->id, 'assigned_user_id' => $agent->id, 'name' => 'Contato autorizado']);
        $listing = $this->listing($owner, ['organization_id' => $branch->organization_id, 'branch_id' => $branch->id]);
        $id = $this->withToken($token)->postJson('/api/imobiliaria/tickets', ['subject' => 'Visita', 'crm_contact_id' => $contact->id, 'commercial_listing_id' => $listing->id])
            ->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/imobiliaria/tickets/'.$id.'/convert')->assertOk()
            ->assertJsonPath('data.crm_contact_id', $contact->id)->assertJsonPath('data.commercial_listing_id', $listing->id)
            ->assertJsonPath('data.branch_id', $branch->id)->assertJsonPath('data.assigned_user_id', $agent->id);
    }

    public function test_bootstrap_preserves_agent_role_and_first_owner_is_stable(): void
    {
        [$owner] = $this->agency('bootstrap');
        [$agent] = $this->agent($owner);
        $organizations = app(OrganizationService::class);
        $this->assertSame($organizations->ensureForTenant($owner)->id, $organizations->ensureForTenant($agent)->id);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $owner->id, 'role' => 'owner']);
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $agent->id, 'role' => 'agent']);
    }

    private function agency(string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => 'imobiliaria', 'active' => true, 'status' => SubscriptionStatus::Active]);
        app(OrganizationService::class)->ensureForTenant($user);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function listing(User $owner, array $data = []): CommercialListing
    {
        $authorization = CommercializationAuthorization::query()->create(['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id]);

        return CommercialListing::query()->create([...$data, 'tenant_id' => $owner->tenant_id, 'user_id' => $owner->id, 'authorization_id' => $authorization->id, 'title' => 'Imóvel QA', 'slug' => 'qa-listing', 'property_type' => 'apartment']);
    }

    private function agent(User $owner): array
    {
        $organizations = app(OrganizationService::class);
        $branch = $organizations->createBranch($owner, ['name' => 'Minha filial']);
        $agent = User::query()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Agente', 'email' => 'agent@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        OrganizationMembership::query()->create(['tenant_id' => $owner->tenant_id, 'organization_id' => $branch->organization_id, 'branch_id' => $branch->id, 'user_id' => $agent->id, 'role' => 'agent', 'status' => 'active']);

        return [$agent, $agent->createToken('test')->plainTextToken, $branch];
    }
}
