<?php

namespace App\Enums;

enum ClientNoteType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case StatusChange = 'status_change';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Nota',
            self::Call => 'Llamada',
            self::Email => 'Correo',
            self::StatusChange => 'Cambio de estatus',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Note => 'chat-bubble-left-ellipsis',
            self::Call => 'phone',
            self::Email => 'envelope',
            self::StatusChange => 'arrow-path',
        };
    }
}
