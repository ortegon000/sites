<?php

namespace App\Enums;

enum DomainCredentialKind: string
{
    case Panel = 'panel';
    case Database = 'database';
    case Ftp = 'ftp';
    case Cms = 'cms';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Panel de hosting',
            self::Database => 'Base de datos',
            self::Ftp => 'FTP',
            self::Cms => 'Gestor de contenido',
            self::Other => 'Otro',
        };
    }
}
