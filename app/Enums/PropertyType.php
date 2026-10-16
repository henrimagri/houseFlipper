<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Land = 'land';
    case Commercial = 'commercial';
    case Auction = 'auction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::House => 'Casa', self::Apartment => 'Apartamento', self::Land => 'Terreno', self::Commercial => 'Comercial', self::Auction => 'Leilão', self::Other => 'Outro'
        };
    }

    public static function options(): array
    {
        return array_column(array_map(fn (self $item) => ['value' => $item->value, 'label' => $item->label()], self::cases()), null, 'value');
    }
}
