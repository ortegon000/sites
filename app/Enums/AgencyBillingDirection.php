<?php

namespace App\Enums;

enum AgencyBillingDirection: string
{
    case WeInvoiceThem = 'we_invoice_them';
    case TheyInvoiceUs = 'they_invoice_us';

    public function label(): string
    {
        return match ($this) {
            self::WeInvoiceThem => 'Nosotros les facturamos',
            self::TheyInvoiceUs => 'Ellos nos facturan',
        };
    }
}
