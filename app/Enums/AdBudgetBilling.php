<?php

namespace App\Enums;

enum AdBudgetBilling: string
{
    case PassThrough = 'pass_through';
    case ClientDirect = 'client_direct';

    public function label(): string
    {
        return match ($this) {
            self::PassThrough => 'Se lo cobramos nosotros',
            self::ClientDirect => 'El cliente paga la plataforma directo',
        };
    }

    /**
     * Whether the ad spend turns into a charge of ours. When the client pays
     * the platform directly the budget is only a reference figure, so it must
     * never reach `charges` — mixing it in would inflate every revenue number.
     */
    public function isBilledByUs(): bool
    {
        return $this === self::PassThrough;
    }
}
