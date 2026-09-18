<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Activo = 'activo';
    case Expirado = 'expirado';
    case Transferido = 'transferido';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Expirado => 'Expirado',
            self::Transferido => 'Transferido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'green',
            self::Expirado => 'red',
            self::Transferido => 'zinc',
        };
    }
}
