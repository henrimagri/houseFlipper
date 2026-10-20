<?php

namespace App\Support;

use Illuminate\Http\Request;

class ProductContext
{
    public const HEADER = 'X-Product-Context';

    public function current(?Request $request = null): ?string
    {
        $request ??= request();
        $value = strtolower(trim((string) $request->header(self::HEADER, '')));
        if ($value === '' && is_string($request->query('product'))) {
            $value = strtolower(trim($request->query('product')));
        }

        return $this->normalize($value);
    }

    public function normalize(?string $slug): ?string
    {
        $slug = strtolower(trim((string) $slug));
        $aliases = [
            'corretor' => 'corretor',
            'broker' => 'corretor',
            'imobiliaria' => 'imobiliaria',
            'agency' => 'imobiliaria',
            'house_flipper' => 'house_flipper',
            'house-flipper' => 'house_flipper',
            'flipper' => 'house_flipper',
        ];

        return $aliases[$slug] ?? null;
    }
}
