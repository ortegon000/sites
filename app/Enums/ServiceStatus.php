<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Cancelado = 'cancelado';
    case Completado = 'completado';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Pausado => 'Pausado',
            self::Cancelado => 'Cancelado',
            self::Completado => 'Completado',
        };
    }
}
