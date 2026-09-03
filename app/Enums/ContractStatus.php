<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case Firmado = 'firmado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviado => 'Enviado',
            self::Firmado => 'Firmado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'zinc',
            self::Enviado => 'blue',
            self::Firmado => 'green',
            self::Cancelado => 'red',
        };
    }

    /**
     * Los que todavía se pueden editar. Un contrato firmado se congela: el
     * documento que se firmó es el que vale, y reescribirlo después sería
     * cambiar lo pactado sin que nadie se entere.
     *
     * @return array<int, self>
     */
    public static function editable(): array
    {
        return [self::Borrador, self::Enviado];
    }
}
