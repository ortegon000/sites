<?php

namespace App\Enums;

enum EmailProviderDriverType: string
{
    case NullDriver = 'null';
    case Mxroute = 'mxroute';
    case Cpanel = 'cpanel';
    case Hostinger = 'hostinger';

    public function label(): string
    {
        return match ($this) {
            self::NullDriver => 'Simulado (sin proveedor real todavía)',
            self::Mxroute => 'MXroute',
            self::Cpanel => 'cPanel',
            self::Hostinger => 'Hostinger',
        };
    }

    /**
     * Drivers that have a working implementation behind them. The other
     * cases exist so the eventual roster is documented in code, but
     * selecting one before its driver class exists would fail at runtime
     * the moment it's used — so the provider form only offers these.
     *
     * @return array<int, self>
     */
    public static function implemented(): array
    {
        return [self::NullDriver];
    }
}
