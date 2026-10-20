<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Captação em andamento',
            self::Granted => 'Autorizado',
            self::Revoked => 'Revogado',
            self::Expired => 'Expirado',
        };
    }
}
