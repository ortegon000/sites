<?php

namespace App\Enums;

enum ChargeStatus: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagado = 'pagado';
    case Vencido = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial => 'Parcial',
            self::Pagado => 'Pagado',
            self::Vencido => 'Vencido',
        };
    }

    /**
     * Color de la insignia en las tablas, para que el semáforo del cobro sea
     * el mismo en el panel interno, el dashboard y el portal del cliente.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'zinc',
            self::Parcial => 'amber',
            self::Pagado => 'green',
            self::Vencido => 'red',
        };
    }

    /**
     * Los cobros que todavía deben dinero. Un cobro parcial sigue abierto:
     * la columna que más se mira en la hoja del dueño es el restante.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pendiente, self::Parcial, self::Vencido];
    }
}
