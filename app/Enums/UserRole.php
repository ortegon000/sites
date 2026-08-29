<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Collaborator = 'collaborator';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Staff => 'Equipo interno',
            self::Collaborator => 'Colaborador externo',
            self::Client => 'Cliente',
        };
    }
}
