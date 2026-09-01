<?php

namespace App\Enums;

enum AdCampaignStatus: string
{
    case Activa = 'activa';
    case Pausada = 'pausada';
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Pausada => 'Pausada',
            self::Finalizada => 'Finalizada',
        };
    }
}
