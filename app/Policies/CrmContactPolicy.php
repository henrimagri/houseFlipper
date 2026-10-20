<?php

namespace App\Policies;

use App\Models\CrmContact;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CrmContactPolicy
{
    public function view(User $user, CrmContact $contact): Response
    {
        return $this->own($user, $contact);
    }

    public function update(User $user, CrmContact $contact): Response
    {
        return $this->own($user, $contact);
    }

    private function own(User $user, CrmContact $contact): Response
    {
        if ((int) $user->tenant_id !== (int) $contact->tenant_id) {
            return Response::denyAsNotFound();
        }
        if ($user->id === $contact->user_id || $user->id === $contact->assigned_user_id) {
            return Response::allow();
        }
        if ($contact->organization_id) {
            $membership = OrganizationMembership::query()
                ->where('organization_id', $contact->organization_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
            if ($membership && ($membership->canManage() || ! $contact->branch_id || (int) $membership->branch_id === (int) $contact->branch_id)) {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }
}
