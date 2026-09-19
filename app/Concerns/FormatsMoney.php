<?php

namespace App\Concerns;

trait FormatsMoney
{
    /**
     * El monto como se ve en la app: miles con coma, dos decimales y la moneda
     * al final ("12,000.00 MXN"), para que los correos no muestren "12000.00".
     */
    protected function formatMoney(float|string $amount, string $currency): string
    {
        return number_format((float) $amount, 2).' '.$currency;
    }
}
