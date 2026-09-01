<?php

namespace App\Enums;

enum DomainManagement: string
{
    case Managed = 'managed';
    case Tracked = 'tracked';

    public function label(): string
    {
        return match ($this) {
            self::Managed => 'Lo registramos y cobramos',
            self::Tracked => 'Solo damos seguimiento',
        };
    }
}
