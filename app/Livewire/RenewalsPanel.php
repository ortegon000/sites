<?php

namespace App\Livewire;

use App\Actions\Renewals\MarkRenewalNotRenewed;
use App\Actions\Renewals\MarkRenewalRenewed;
use App\Actions\Renewals\NotifyClientOfRenewal;
use App\Enums\RenewalStatus;
use App\Models\Client;
use App\Models\Renewal;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lo que caduca de un cliente: sus dominios, sus licencias y sus servicios
 * anuales, con el ciclo explícito —por avisar, avisado, renovó, no renovó—.
 *
 * El tablero general contesta "qué hay que perseguir esta semana"; esta tarjeta
 * contesta la otra pregunta, la que llega por teléfono: "¿qué se le vence a
 * este cliente y qué le dijimos?". Por eso vive en su expediente y guarda el
 * historial de ciclos cerrados, que en el tablero se pierde de vista.
 *
 * Las renovaciones cuelgan del cliente, no del proyecto: lo que caduca suele
 * ser el dominio o la licencia de alguien que ni proyecto tiene.
 */
class RenewalsPanel extends Component
{
    public Client $client;

    /** Qué está a la vista: los ciclos abiertos o los ya decididos. */
    public string $renewalsTab = 'abiertas';

    public ?int $editingRenewalId = null;

    public ?string $renewalAmount = null;

    public ?string $renewalNotes = null;

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
    }

    /**
     * @return Collection<int, Renewal>
     */
    #[Computed]
    public function renewals(): Collection
    {
        $query = $this->renewalsQuery()->with(['renewable', 'service']);

        return $this->renewalsTab === 'historial'
            ? $query->whereNotIn('status', RenewalStatus::open())->orderByDesc('due_date')->get()
            : $query->whereIn('status', RenewalStatus::open())->orderBy('due_date')->get();
    }

    /**
     * @return array{abiertas: int, historial: int}
     */
    #[Computed]
    public function renewalCounts(): array
    {
        return [
            'abiertas' => $this->renewalsQuery()->whereIn('status', RenewalStatus::open())->count(),
            'historial' => $this->renewalsQuery()->whereNotIn('status', RenewalStatus::open())->count(),
        ];
    }

    public function notifyClient(int $renewalId, NotifyClientOfRenewal $action): void
    {
        Gate::authorize('update', $this->client);

        if (! $action->handle($this->findRenewal($renewalId))) {
            Flux::toast(variant: 'danger', text: __('Este cliente no tiene ningún contacto con correo. Agrega uno arriba, en Contactos.'));

            return;
        }

        unset($this->renewals, $this->renewalCounts);

        Flux::toast(variant: 'success', text: __('Aviso enviado al cliente.'));
    }

    public function openAmountModal(int $renewalId): void
    {
        Gate::authorize('update', $this->client);

        $renewal = $this->findRenewal($renewalId);

        $this->editingRenewalId = $renewal->id;
        $this->renewalAmount = $renewal->amount;
        $this->renewalNotes = $renewal->notes;
        $this->resetValidation();

        $this->modal('client-renewal-form')->show();
    }

    public function saveRenewal(): void
    {
        Gate::authorize('update', $this->client);

        $renewal = $this->findRenewal($this->editingRenewalId ?? 0);

        $validated = $this->validate([
            'renewalAmount' => ['nullable', 'numeric', 'min:0'],
            'renewalNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $renewal->update([
            'amount' => $validated['renewalAmount'],
            'notes' => $validated['renewalNotes'],
        ]);

        unset($this->renewals);

        $this->modal('client-renewal-form')->close();

        Flux::toast(variant: 'success', text: __('Renovación actualizada.'));
    }

    public function closeAmountModal(): void
    {
        $this->modal('client-renewal-form')->close();
    }

    public function markRenewed(int $renewalId, MarkRenewalRenewed $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findRenewal($renewalId));

        unset($this->renewals, $this->renewalCounts);

        Flux::toast(variant: 'success', text: __('Renovación registrada.'));
    }

    public function markNotRenewed(int $renewalId, MarkRenewalNotRenewed $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findRenewal($renewalId));

        unset($this->renewals, $this->renewalCounts);

        Flux::toast(variant: 'success', text: __('Baja registrada.'));
    }

    /**
     * @return Builder<Renewal>
     */
    private function renewalsQuery(): Builder
    {
        return Renewal::query()->where('client_id', $this->client->id);
    }

    /**
     * El id llega del navegador, así que se busca dentro de los ciclos de este
     * cliente: si no, se podría tocar la renovación de otro.
     */
    private function findRenewal(int $renewalId): Renewal
    {
        return $this->renewalsQuery()->with(['client', 'renewable'])->findOrFail($renewalId);
    }

    public function render(): View
    {
        return view('livewire.renewals-panel');
    }
}
