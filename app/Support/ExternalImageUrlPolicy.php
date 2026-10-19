<?php

namespace App\Support;

class ExternalImageUrlPolicy
{
    public static function allows(mixed $value): bool
    {
        return ExternalUrlPolicy::allows($value);
    }
}
