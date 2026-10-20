<?php

namespace App\Policies;

use App\Models\CommercializationAuthorization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommercializationAuthorizationPolicy
{
    public function view(User $user, CommercializationAuthorization $authorization): Response
    {
        return $this->own($user, $authorization);
    }

    public function update(User $user, CommercializationAuthorization $authorization): Response
    {
        return $this->own($user, $authorization);
    }

    private function own(User $user, CommercializationAuthorization $authorization): Response
    {
        return $user->id === $authorization->user_id && (int) $user->tenant_id === (int) $authorization->tenant_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
