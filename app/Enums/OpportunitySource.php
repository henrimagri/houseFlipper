<?php

namespace App\Enums;

enum OpportunitySource: string
{
    case Manual = 'manual';
    case Olx = 'olx';
    case Zap = 'zap';
    case VivaReal = 'vivareal';
    case QuintoAndar = 'quintoandar';
    case Facebook = 'facebook';
    case Realtor = 'realtor';
    case Auction = 'auction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual', self::Olx => 'OLX', self::Zap => 'ZAP', self::VivaReal => 'VivaReal', self::QuintoAndar => 'QuintoAndar', self::Facebook => 'Facebook', self::Realtor => 'Corretor', self::Auction => 'Leilão', self::Other => 'Outro'
        };
    }

    public static function options(): array
    {
        return array_column(array_map(fn (self $item) => ['value' => $item->value, 'label' => $item->label()], self::cases()), null, 'value');
    }
}
