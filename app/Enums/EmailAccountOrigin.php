<?php

namespace App\Enums;

enum EmailAccountOrigin: string
{
    case Provisioned = 'provisioned';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Provisioned => 'Creada desde el CRM',
            self::Imported => 'Existente, vinculada',
        };
    }
}
