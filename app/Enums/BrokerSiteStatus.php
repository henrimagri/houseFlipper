<?php

namespace App\Enums;

enum BrokerSiteStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function isPubliclyReachable(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Teste gratuito',
            self::Active => 'Ativa',
            self::PastDue => 'Pagamento pendente',
            self::Grace => 'Período de tolerância',
            self::Suspended => 'Suspensa',
            self::Cancelled => 'Cancelada',
        };
    }
}
