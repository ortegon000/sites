<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ContractPrintController extends Controller
{
    /**
     * La versión imprimible del contrato: una página limpia que el navegador
     * guarda como PDF. Sale del controlador y no de un componente Livewire
     * porque no tiene nada interactivo y necesita su propio layout, sin la
     * barra lateral de la aplicación.
     */
    public function __invoke(Contract $contract): View
    {
        Gate::authorize('view', $contract->client);

        return view('contracts.print', ['contract' => $contract->load('client')]);
    }
}
