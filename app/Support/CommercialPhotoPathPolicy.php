<?php

namespace App\Support;

final class CommercialPhotoPathPolicy
{
    public static function allows(string $path): bool
    {
        return preg_match('#\Acommercial-listings/[0-9]+/[A-Za-z0-9][A-Za-z0-9._-]*\z#', $path) === 1;
    }

    public static function evidenceAllows(string $path): bool
    {
        return preg_match('#\Acapture-evidence/[0-9]+/[0-9]+/[A-Za-z0-9][A-Za-z0-9._-]*\z#', $path) === 1;
    }
}
