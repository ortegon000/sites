<?php

namespace App\Enums;

/**
 * El ciclo de una renovación. Antes no había dónde registrar "ya le avisé,
 * estoy esperando", que es el estado en el que vive la mitad del trabajo.
 */
enum RenewalStatus: string
{
    case PorAvisar = 'por_avisar';
    case Avisado = 'avisado';
    case Renovado = 'renovado';
    case NoRenovado = 'no_renovado';

    public function label(): string
    {
        return match ($this) {
            self::PorAvisar => 'Por avisar',
            self::Avisado => 'Avisado',
            self::Renovado => 'Renovó',
            self::NoRenovado => 'No renovó',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PorAvisar => 'amber',
            self::Avisado => 'blue',
            self::Renovado => 'green',
            self::NoRenovado => 'zinc',
        };
    }

    /**
     * Los ciclos que siguen esperando una decisión.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::PorAvisar, self::Avisado];
    }
}
