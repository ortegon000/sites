<?php

namespace App\Enums;

enum EmailProviderDriverType: string
{
    case NullDriver = 'null';
    case Manual = 'manual';
    case Mxroute = 'mxroute';
    case Cpanel = 'cpanel';
    case Hostinger = 'hostinger';

    public function label(): string
    {
        return match ($this) {
            self::NullDriver => 'Simulado (sin proveedor real todavía)',
            self::Manual => 'Manual (lo administra la agencia)',
            self::Mxroute => 'MXroute',
            self::Cpanel => 'cPanel',
            self::Hostinger => 'Hostinger',
        };
    }

    /**
     * Whether mailbox passwords have to be kept in our own database. A driver
     * that talks to a real API can always reset a password on demand, so we
     * never store one; a manually administered provider has no API to ask, so
     * losing the password here means losing it for good.
     */
    public function storesPasswordLocally(): bool
    {
        return $this === self::Manual;
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
        return [self::NullDriver, self::Manual];
    }
}
