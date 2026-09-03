<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Website = 'website';
    case Hosting = 'hosting';
    case Ssl = 'ssl';
    case Domain = 'domain';
    case Email = 'email';
    case Maintenance = 'maintenance';
    case AdsManagement = 'ads_management';
    case AdsBudget = 'ads_budget';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Sitio web',
            self::Hosting => 'Hosting',
            self::Ssl => 'Certificado SSL',
            self::Domain => 'Dominio',
            self::Email => 'Correo',
            self::Maintenance => 'Mantenimiento',
            self::AdsManagement => 'Gestión de campañas',
            self::AdsBudget => 'Inversión publicitaria',
            self::Other => 'Otro',
        };
    }

    /**
     * El tipo de proyecto que abre una cotización de esta categoría cuando se
     * marcó como proyecto. Hosting, SSL y dominio caen en "otro" a propósito:
     * son trabajo de infraestructura que no describe un proyecto en sí.
     */
    public function projectType(): ProjectType
    {
        return match ($this) {
            self::Website => ProjectType::Web,
            self::Maintenance => ProjectType::Maintenance,
            self::AdsManagement, self::AdsBudget => ProjectType::Ads,
            self::Email => ProjectType::Email,
            self::Hosting, self::Ssl, self::Domain, self::Other => ProjectType::Other,
        };
    }

    /**
     * Categories that describe work on a specific domain, so the service form
     * offers to link one and the resulting charge can say which domain it is for.
     */
    public function belongsToDomain(): bool
    {
        return in_array($this, [self::Domain, self::Email, self::Hosting, self::Ssl], true);
    }
}
