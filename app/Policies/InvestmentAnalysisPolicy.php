<?php

namespace App\Policies;

use App\Models\InvestmentAnalysis;
use App\Models\User;

class InvestmentAnalysisPolicy
{
    public function view(User $user, InvestmentAnalysis $analysis): bool
    {
        return $user->id === $analysis->user_id;
    }

    public function update(User $user, InvestmentAnalysis $analysis): bool
    {
        return $user->id === $analysis->user_id;
    }
}
