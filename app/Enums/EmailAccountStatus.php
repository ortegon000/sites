<?php

namespace App\Enums;

enum EmailAccountStatus: string
{
    case Activa = 'activa';
    case Suspendida = 'suspendida';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
        };
    }
}
