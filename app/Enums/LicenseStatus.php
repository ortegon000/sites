<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Activa = 'activa';
    case Vencida = 'vencida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Vencida => 'Vencida',
            self::Cancelada => 'Cancelada',
        };
    }
}
