<?php

namespace App\Enums;

/**
 * El trabajo cotizado vive antes del cobro: hay filas del archivo del dueño en
 * estatus "Pendiente" sin costo, con el precio escrito en las notas. Eso no es
 * una línea cobrable todavía —nadie ha aceptado nada— pero tampoco es nada.
 */
enum QuoteStatus: string
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Expirada = 'expirada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
            self::Expirada => 'Expirada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'zinc',
            self::Enviada => 'blue',
            self::Aceptada => 'green',
            self::Rechazada => 'red',
            self::Expirada => 'amber',
        };
    }

    /**
     * Las que siguen esperando respuesta del cliente.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Borrador, self::Enviada];
    }

    /**
     * Las que ya no esperan nada: se decidieron o se les pasó la vigencia. La
     * expirada entra aquí —aunque nadie la haya contestado— para que ninguna
     * cotización quede fuera de las dos listas.
     *
     * @return array<int, self>
     */
    public static function archived(): array
    {
        return [self::Aceptada, self::Rechazada, self::Expirada];
    }
}
