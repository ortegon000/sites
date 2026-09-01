<?php

namespace App\Enums;

enum DomainEmailManagement: string
{
    case Managed = 'managed';
    case NotManaged = 'not_managed';

    public function label(): string
    {
        return match ($this) {
            self::Managed => 'Administramos el correo',
            self::NotManaged => 'No administramos el correo',
        };
    }
}
