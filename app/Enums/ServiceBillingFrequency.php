<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ServiceBillingFrequency: string
{
    case OneTime = 'one_time';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Pago único',
            self::Biweekly => 'Quincenal',
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
            self::Biweekly, self::Monthly, self::Quarterly, self::Semiannual, self::Annual => true,
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
            /** Dos cobros al mes anclados al día de inicio —el 3 y el 18, por
             *  ejemplo—, en vez de sumar quince días y correrse de mes en mes. */
            self::Biweekly => $date->day < 16
                ? $date->setDay(min($date->day + 15, $date->daysInMonth))
                : $date->addMonthNoOverflow()->setDay($date->day - 15),
            self::Monthly => $date->addMonthNoOverflow(),
            self::Quarterly => $date->addMonthsNoOverflow(3),
            self::Semiannual => $date->addMonthsNoOverflow(6),
            self::Annual => $date->addYear(),
            self::OneTime, self::Installment => null,
        };
    }
}
