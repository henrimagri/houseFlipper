<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }
}
