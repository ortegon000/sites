<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ServiceBillingFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Pago único',
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Semiannual => 'Semestral',
            self::Annual => 'Anual',
            self::Installment => 'Pagos a plazos',
        };
    }

    /**
     * Whether this frequency keeps generating charges on `next_charge_date`.
     * One-time services charge once, and installments are driven by their own
     * `service_installments` rows instead.
     */
    public function isRecurring(): bool
    {
        return match ($this) {
            self::Monthly, self::Quarterly, self::Semiannual, self::Annual => true,
            self::OneTime, self::Installment => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function recurring(): array
    {
        return array_values(array_filter(self::cases(), fn (self $frequency) => $frequency->isRecurring()));
    }

    /**
     * The next charge date after billing on `$date`, or null for frequencies
     * that do not recur.
     */
    public function advanceFrom(CarbonImmutable $date): ?CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $date->addMonthNoOverflow(),
            self::Quarterly => $date->addMonthsNoOverflow(3),
            self::Semiannual => $date->addMonthsNoOverflow(6),
            self::Annual => $date->addYear(),
            self::OneTime, self::Installment => null,
        };
    }
}
