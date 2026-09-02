<?php

namespace Database\Seeders;

use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Models\EmailProvider;
use Illuminate\Database\Seeder;

class EmailProviderSeeder extends Seeder
{
    /**
     * Los dos proveedores del demo: uno simulado, que hace de proveedor con API
     * y por eso nunca guarda contraseñas, y uno manual, que sí las guarda
     * porque no hay API que pueda resetearlas. Los dominios y buzones que los
     * usan los crea `ProjectSeeder`.
     */
    public function run(): void
    {
        EmailProvider::factory()->create([
            'name' => 'MXroute (simulado)',
            'driver' => EmailProviderDriverType::NullDriver,
            'status' => EmailProviderStatus::Activo,
        ]);

        EmailProvider::factory()->create([
            'name' => 'Proveedor manual (sin API)',
            'driver' => EmailProviderDriverType::Manual,
            'status' => EmailProviderStatus::Activo,
            'connection_settings' => [
                'imap_host' => 'imap.proveedor-manual.test',
                'imap_port' => '993',
                'smtp_host' => 'smtp.proveedor-manual.test',
                'smtp_port' => '587',
            ],
        ]);

        EmailProvider::factory()->create([
            'name' => 'cPanel del hosting viejo',
            'driver' => EmailProviderDriverType::Manual,
            'status' => EmailProviderStatus::Inactivo,
        ]);
    }
}
