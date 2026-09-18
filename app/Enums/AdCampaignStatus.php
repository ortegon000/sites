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

    public function color(): string
    {
        return match ($this) {
            self::Activa => 'green',
            self::Pausada => 'amber',
            self::Finalizada => 'zinc',
        };
    }
}
