<?php

namespace App\Enums;

enum ServiceBillingFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Annual = 'annual';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Pago único',
            self::Monthly => 'Mensual',
            self::Annual => 'Anual',
            self::Installment => 'Pagos a plazos',
        };
    }
}
