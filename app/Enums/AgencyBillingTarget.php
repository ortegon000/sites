<?php

namespace App\Enums;

enum AgencyBillingTarget: string
{
    case Agency = 'agency';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Agency => 'A la agencia',
            self::Client => 'Al cliente final',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Agency => 'El trabajo llega por la agencia y la factura va a ella, no al cliente que se atiende.',
            self::Client => 'La agencia trae o representa el trabajo, pero la factura va al cliente final.',
        };
    }
}
