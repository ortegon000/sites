<?php

namespace App\Enums;

enum ClientType: string
{
    case Prospect = 'prospect';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospecto',
            self::Client => 'Cliente',
        };
    }
}
