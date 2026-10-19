<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OpportunityPolicy
{
    public function view(User $user, Opportunity $opportunity): Response
    {
        return $this->ownershipResponse($user, $opportunity);
    }

    public function update(User $user, Opportunity $opportunity): Response
    {
        return $this->ownershipResponse($user, $opportunity);
    }

    public function delete(User $user, Opportunity $opportunity): Response
    {
        return $this->ownershipResponse($user, $opportunity);
    }

    private function ownershipResponse(User $user, Opportunity $opportunity): Response
    {
        return $user->id === $opportunity->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
