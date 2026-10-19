<?php

namespace App\Policies;

use App\Models\InvestmentAnalysis;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvestmentAnalysisPolicy
{
    public function view(User $user, InvestmentAnalysis $analysis): Response
    {
        return $user->id === $analysis->user_id && $analysis->opportunity()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, InvestmentAnalysis $analysis): Response
    {
        return $this->view($user, $analysis);
    }
}
