<?php

namespace App\Support;

final class OpportunityPhotoPathPolicy
{
    public static function allows(string $path): bool
    {
        return preg_match('#\Aopportunities/[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*\z#', $path) === 1;
    }
}
