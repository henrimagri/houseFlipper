<?php

namespace App\Services\Workspace;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Str;

class OrganizationService
{
    public function ensureForTenant(User $user): Organization
    {
        $org = Organization::query()->firstOrCreate(
            ['tenant_id' => $user->tenant_id],
            [
                'name' => $user->tenant?->nome ?: ($user->name.' Imobiliária'),
                'slug' => $this->uniqueSlug(Str::slug($user->tenant?->slug ?: $user->name) ?: 'imobiliaria-'.$user->id),
                'kind' => 'imobiliaria',
                'ativo_flg' => true,
            ],
        );
        OrganizationMembership::query()->firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'role' => 'owner',
                'status' => 'active',
            ],
        );

        return $org;
    }

    public function membership(User $user): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'active')
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
        if ($limited && $branchId && $limited !== $branchId) {
            abort(403, 'Filial não autorizada.');
        }
    }

    public function addMember(User $actor, array $data): OrganizationMembership
    {
        $membership = $this->requireManager($actor);
        $org = $membership->organization;
        $user = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($data['user_id'])
            ->firstOrFail();

        if ($data['branch_id'] ?? null) {
            Branch::query()
                ->where('organization_id', $org->id)
                ->whereKey($data['branch_id'])
                ->firstOrFail();
        }

        return OrganizationMembership::query()->updateOrCreate(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            [
                'tenant_id' => $actor->tenant_id,
                'branch_id' => $data['branch_id'] ?? null,
                'role' => in_array($data['role'] ?? 'agent', ['owner', 'manager', 'agent'], true) ? $data['role'] : 'agent',
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] : 'active',
                'specialties' => $data['specialties'] ?? null,
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
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
