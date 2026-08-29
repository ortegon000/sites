<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Nuevo = 'nuevo';
    case Contactado = 'contactado';
    case PropuestaEnviada = 'propuesta_enviada';
    case Ganado = 'ganado';
    case Perdido = 'perdido';
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Contactado => 'Contactado',
            self::PropuestaEnviada => 'Propuesta enviada',
            self::Ganado => 'Ganado',
            self::Perdido => 'Perdido',
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function forType(ClientType $type): array
    {
        return match ($type) {
            ClientType::Prospect => [self::Nuevo, self::Contactado, self::PropuestaEnviada, self::Ganado, self::Perdido],
            ClientType::Client => [self::Activo, self::Inactivo],
        };
    }
}
