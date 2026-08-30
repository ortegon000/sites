<?php

namespace App\Enums;

enum ChargeStatus: string
{
    case Pendiente = 'pendiente';
    case Pagado = 'pagado';
    case Vencido = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagado => 'Pagado',
            self::Vencido => 'Vencido',
        };
    }
}
