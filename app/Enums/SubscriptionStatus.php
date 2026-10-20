<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }
}
