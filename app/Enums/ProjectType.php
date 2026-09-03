<?php

namespace App\Enums;

enum ProjectType: string
{
    case Web = 'web';
    case Maintenance = 'maintenance';
    case Ads = 'ads';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Proyecto web',
            self::Maintenance => 'Mantenimiento web',
            self::Ads => 'Campaña de ads',
            self::Email => 'Correo',
            self::Other => 'Otro',
        };
    }

    /**
     * Services this type of project usually bills, used to prefill the creation
     * form. Amounts are left out on purpose: they change per client, so staff
     * fills them in (and removes whatever does not apply) before saving.
     *
     * @return array<int, array{name: string, category: ServiceCategory, billing_frequency: ServiceBillingFrequency}>
     */
    public function serviceTemplate(): array
    {
        return match ($this) {
            self::Web => [
                ['name' => 'Sitio web', 'category' => ServiceCategory::Website, 'billing_frequency' => ServiceBillingFrequency::OneTime],
                ['name' => 'Hosting', 'category' => ServiceCategory::Hosting, 'billing_frequency' => ServiceBillingFrequency::Annual],
                ['name' => 'Certificado SSL', 'category' => ServiceCategory::Ssl, 'billing_frequency' => ServiceBillingFrequency::Annual],
                ['name' => 'Correo', 'category' => ServiceCategory::Email, 'billing_frequency' => ServiceBillingFrequency::Annual],
                ['name' => 'Dominio', 'category' => ServiceCategory::Domain, 'billing_frequency' => ServiceBillingFrequency::Annual],
            ],
            self::Maintenance => [
                ['name' => 'Mantenimiento web', 'category' => ServiceCategory::Maintenance, 'billing_frequency' => ServiceBillingFrequency::Monthly],
            ],
            self::Ads => [
                ['name' => 'Gestión de campañas', 'category' => ServiceCategory::AdsManagement, 'billing_frequency' => ServiceBillingFrequency::Monthly],
            ],
            self::Email => [
                ['name' => 'Correo', 'category' => ServiceCategory::Email, 'billing_frequency' => ServiceBillingFrequency::Annual],
            ],
            self::Other => [],
        };
    }
}
