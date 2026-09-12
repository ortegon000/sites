<?php

namespace App\Actions\Quotes;

use App\Actions\Services\DeleteService;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use RuntimeException;

class UndoAcceptQuote
{
    public function __construct(private DeleteService $deleteService) {}

    /**
     * Deshace una aceptación por error: borra las líneas cobrables que nació
     * de cada renglón y regresa la cotización a como estaba antes de
     * aceptarse. Ninguna se toca si alguna ya tiene un cobro con abono, para
     * no perder esa constancia -en ese caso hay que cancelarlas a mano desde
     * Trabajos y cobros.
     *
     * El proyecto que se haya abierto no se borra: si nació de esta
     * aceptación (`is_project`), se desliga y queda vacío para que se borre a
     * mano si ya no aplica; si la cotización ya vivía dentro de un proyecto
     * desde que se capturó, ese vínculo no se toca.
     *
     * El estatus del cliente (p. ej. un prospecto ganado) tampoco se revierte
     * aquí: puede depender de más que esta sola cotización.
     */
    public function handle(Quote $quote): void
    {
        $quote->loadMissing('lineItems.service');

        $undeletable = $quote->lineItems->filter(
            fn (QuoteLineItem $item) => $item->service && ! $item->service->canBeDeleted()
        );

        if ($undeletable->isNotEmpty()) {
            throw new RuntimeException("La cotización [{$quote->name}] tiene renglones con cobros abonados y no se puede deshacer.");
        }

        foreach ($quote->lineItems as $item) {
            if ($item->service) {
                $this->deleteService->handle($item->service);
                $item->update(['service_id' => null]);
            }
        }

        $quote->update([
            'status' => $quote->sent_at !== null ? QuoteStatus::Enviada : QuoteStatus::Borrador,
            'decided_at' => null,
            'project_id' => $quote->is_project ? null : $quote->project_id,
        ]);
    }
}
