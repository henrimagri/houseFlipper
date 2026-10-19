<?php

namespace App\Support;

class OpportunityPhotoUrl
{
    public static function for(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return ExternalImageUrlPolicy::allows($path) ? $path : null;
        }
        if (! OpportunityPhotoPathPolicy::allows($path)) {
            return null;
        }

        $base = rtrim((string) config('opportunities.photo_url'), '/');
        if ($base === '') {
            return '/storage/'.$path;
        }
        if (! ExternalImageUrlPolicy::allows($base.'/probe.jpg')) {
            return null;
        }

        return $base.'/'.$path;
    }
}
