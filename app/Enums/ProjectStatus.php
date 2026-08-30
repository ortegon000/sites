<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Completado = 'completado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Pausado => 'Pausado',
            self::Completado => 'Completado',
            self::Cancelado => 'Cancelado',
        };
    }
}
