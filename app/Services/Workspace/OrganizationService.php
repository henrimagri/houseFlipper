<?php

namespace App\Services\Workspace;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function ensureForTenant(User $user): Organization
    {
        return DB::transaction(function () use ($user) {
            // Serialize first-owner bootstrap on the existing tenant, not on a missing membership.
            Tenant::query()->whereKey($user->tenant_id)->lockForUpdate()->firstOrFail();
            $org = Organization::withoutGlobalScope(TenantScope::class)->where('tenant_id', $user->tenant_id)->first();
            if ($org) {
                $member = OrganizationMembership::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $user->tenant_id)->where('organization_id', $org->id)->where('user_id', $user->id)->first();
                if ($member) {
                    abort_unless($member->isActive(), 403, 'Usuário sem vínculo ativo com a imobiliária.');

                    return $org;
                }
                abort_if(OrganizationMembership::withoutGlobalScope(TenantScope::class)->where('organization_id', $org->id)->exists(), 403, 'Solicite um convite à gestão da imobiliária.');
            }
            // The first tenant user is the account creator in the existing registration model.
            $creatorId = User::query()->where('tenant_id', $user->tenant_id)->orderBy('id')->value('id');
            abort_unless((int) $creatorId === (int) $user->id, 403, 'Somente o responsável original pode iniciar a imobiliária.');
            $org ??= Organization::withoutGlobalScope(TenantScope::class)->firstOrCreate(
                ['tenant_id' => $user->tenant_id],
                [
                    'name' => $user->tenant?->nome ?: ($user->name.' Imobiliária'),
                    'slug' => $this->uniqueSlug(Str::slug($user->tenant?->slug ?: $user->name) ?: 'imobiliaria-'.$user->id),
                    'kind' => 'imobiliaria',
                    'ativo_flg' => true,
                ],
            );
            OrganizationMembership::withoutGlobalScope(TenantScope::class)->firstOrCreate(
                ['organization_id' => $org->id, 'user_id' => $user->id],
                [
                    'tenant_id' => $user->tenant_id,
                    'role' => 'owner',
                    'status' => 'active',
                ],
            );

            return $org;
        }, 3);
    }

    public function membership(User $user): ?OrganizationMembership
    {
        return OrganizationMembership::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->withoutGlobalScope(TenantScope::class)->where('tenant_id', $user->tenant_id))
            ->first();
    }

    public function requireMembership(User $user): OrganizationMembership
    {
        $membership = $this->membership($user);
        abort_if(! $membership, 403, 'Usuário sem vínculo ativo com a imobiliária.');

        return $membership;
    }

    public function requireManager(User $user): OrganizationMembership
    {
        $membership = $this->requireMembership($user);
        abort_unless($membership->canManage(), 403, 'Apenas gestores da imobiliária podem executar esta ação.');

        return $membership;
    }

    public function visibleBranchId(OrganizationMembership $membership): ?int
    {
        if ($membership->canManage()) {
            return null;
        }

        return $membership->branch_id ? (int) $membership->branch_id : null;
    }

    public function assertBranchAccess(OrganizationMembership $membership, ?int $branchId): void
    {
        $limited = $this->visibleBranchId($membership);
        if ($limited && $limited !== $branchId) {
            abort(403, 'Filial não autorizada.');
        }
        if ($branchId !== null && ! Branch::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $membership->tenant_id)
            ->where('organization_id', $membership->organization_id)
            ->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Filial não pertence à imobiliária.']);
        }
    }

    public function addMember(User $actor, array $data): OrganizationMembership
    {
        $membership = $this->requireManager($actor);
        $orgId = $membership->organization_id;
        $user = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($data['user_id'])
            ->firstOrFail();

        if ($data['branch_id'] ?? null) {
            Branch::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $actor->tenant_id)
                ->where('organization_id', $orgId)
                ->whereKey($data['branch_id'])
                ->firstOrFail();
        }

        $existing = OrganizationMembership::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $actor->tenant_id)->where('organization_id', $orgId)->where('user_id', $user->id)->first();
        $role = $data['role'] ?? $existing?->role ?? 'agent';
        $status = $data['status'] ?? $existing?->status ?? 'active';

        return OrganizationMembership::withoutGlobalScope(TenantScope::class)->updateOrCreate(
            ['tenant_id' => $actor->tenant_id, 'organization_id' => $orgId, 'user_id' => $user->id],
            [
                'tenant_id' => $actor->tenant_id,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $existing?->branch_id,
                'role' => in_array($role, ['owner', 'manager', 'agent'], true) ? $role : 'agent',
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                'specialties' => $data['specialties'] ?? $existing?->specialties,
            ],
        );
    }

    public function createBranch(User $actor, array $data): Branch
    {
        $membership = $this->requireManager($actor);

        return Branch::query()->create([
            'tenant_id' => $actor->tenant_id,
            'organization_id' => $membership->organization_id,
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'ativo_flg' => true,
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Organization::withoutGlobalScope(TenantScope::class)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
