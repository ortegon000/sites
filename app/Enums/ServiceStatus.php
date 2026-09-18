<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Pendiente = 'pendiente';
    case Activo = 'activo';
    case Cancelado = 'cancelado';
    case Terminado = 'terminado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Activo => 'Activo',
            self::Cancelado => 'Cancelado',
            self::Terminado => 'Terminado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'amber',
            self::Activo => 'green',
            self::Cancelado => 'zinc',
            self::Terminado => 'blue',
        };
    }
}
