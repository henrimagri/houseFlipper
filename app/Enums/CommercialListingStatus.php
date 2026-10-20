<?php

namespace App\Enums;

enum CommercialListingStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Published = 'published';
    case Paused = 'paused';
    case Sold = 'sold';
    case Rented = 'rented';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Ready => 'Pronto',
            self::Published => 'Publicado',
            self::Paused => 'Pausado',
            self::Sold => 'Vendido',
            self::Rented => 'Alugado',
            self::Withdrawn => 'Retirado',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
