<?php

namespace App\Enums;

enum DomainCredentialKind: string
{
    case Registrar = 'registrar';
    case Panel = 'panel';
    case Database = 'database';
    case Ftp = 'ftp';
    case Cms = 'cms';

    public function label(): string
    {
        return match ($this) {
            self::Registrar => 'Cuenta del registrador',
            self::Panel => 'Panel de hosting',
            self::Database => 'Base de datos',
            self::Ftp => 'FTP',
            self::Cms => 'Gestor de contenido',
        };
    }
}
