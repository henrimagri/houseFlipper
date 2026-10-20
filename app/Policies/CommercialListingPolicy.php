<?php

namespace App\Policies;

use App\Models\CommercialListing;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommercialListingPolicy
{
    public function view(User $user, CommercialListing $listing): Response
    {
        return $this->own($user, $listing);
    }

    public function update(User $user, CommercialListing $listing): Response
    {
        return $this->own($user, $listing);
    }

    public function delete(User $user, CommercialListing $listing): Response
    {
        return $this->own($user, $listing);
    }

    private function own(User $user, CommercialListing $listing): Response
    {
        if ($user->id === $listing->user_id && (int) $user->tenant_id === (int) $listing->tenant_id) {
            return Response::allow();
        }
        if ($listing->organization_id && (int) $user->tenant_id === (int) $listing->tenant_id) {
            $membership = \App\Models\OrganizationMembership::query()
                ->where('organization_id', $listing->organization_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
            if ($membership && ($membership->canManage() || ! $listing->branch_id || (int) $membership->branch_id === (int) $listing->branch_id)) {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }
}
