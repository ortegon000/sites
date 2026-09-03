<?php

namespace Database\Seeders;

use App\Actions\Contracts\DraftContract;
use App\Actions\Contracts\SignContract;
use App\Enums\ContractStatus;
use App\Models\Client;
use Illuminate\Database\Seeder;

/**
 * Dos contratos con los servicios que el cliente ya tiene: uno firmado y
 * congelado, y otro en borrador esperando revisión. Se generan con la misma
 * acción que usa la app, así que el texto sembrado es exactamente el que
 * produciría el botón.
 */
class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $draft = app(DraftContract::class);

        $demo = Client::where('name', 'Cliente Demo')->firstOrFail();
        $clinic = Client::where('name', 'Clínica Sur')->firstOrFail();

        $signed = $draft->handle(
            $demo,
            $demo->services()->with('items')->whereNotNull('project_id')->get(),
            'Contrato de servicios web y correo',
            now()->subMonths(6)->toDateString(),
            now()->addMonths(6)->toDateString(),
        );

        app(SignContract::class)->handle($signed, 'Juan Pérez');

        $pending = $draft->handle(
            $clinic,
            $clinic->services()->with('items')->whereNull('project_id')->get(),
            'Contrato de mantenimiento y soporte',
            now()->toDateString(),
            now()->addYear()->toDateString(),
        );

        $pending->update([
            'status' => ContractStatus::Enviado,
            'sent_at' => now()->subDays(2),
            'notes' => 'Se lo mandamos por correo; quedó de revisarlo con su contador.',
        ]);
    }
}
