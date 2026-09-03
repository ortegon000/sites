<?php

namespace App\Livewire;

use App\Actions\Charges\DeleteChargePayment;
use App\Actions\Charges\MarkChargeAsPaid;
use App\Actions\Charges\RecordChargePayment;
use App\Actions\Charges\UpdateCharge;
use App\Models\Charge;
use App\Models\ChargePayment;
use App\Models\Client;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Los cobros de un cliente con sus abonos.
 *
 * Recibe siempre el cliente y opcionalmente un proyecto: con proyecto es la
 * tarjeta de cobros de ese trabajo, y sin él es el estado de cuenta del
 * cliente, que es donde aparecen los cobros de las líneas sueltas —las que no
 * cuelgan de ningún proyecto y son la mayoría.
 */
class ChargesPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public ?int $editingChargeId = null;

    public ?string $chargeConcept = null;

    public string $chargeAmount = '';

    public string $chargeDueDate = '';

    public ?int $payingChargeId = null;

    public string $paymentAmount = '';

    public string $paymentPaidOn = '';

    public ?string $paymentMethod = null;

    public ?string $paymentAccount = null;

    public ?string $paymentReference = null;

    public ?string $paymentInvoiceReference = null;

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
    }

    /**
     * @return Collection<int, Charge>
     */
    #[Computed]
    public function charges(): Collection
    {
        return $this->chargesQuery()
            ->with(['service.project', 'payments'])
            ->orderBy('due_date')
            ->get();
    }

    #[Computed]
    public function payingCharge(): ?Charge
    {
        if ($this->payingChargeId === null) {
            return null;
        }

        return $this->chargesQuery()
            ->with(['service', 'payments' => fn ($query) => $query->orderBy('paid_on')->orderBy('id')])
            ->find($this->payingChargeId);
    }

    public function markChargeAsPaid(int $chargeId, MarkChargeAsPaid $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findCharge($chargeId));

        unset($this->charges);

        Flux::toast(variant: 'success', text: __('Cobro marcado como pagado.'));
    }

    public function openChargeModal(int $chargeId): void
    {
        Gate::authorize('update', $this->client);

        $charge = $this->findCharge($chargeId);

        $this->editingChargeId = $charge->id;
        $this->chargeConcept = $charge->concept;
        $this->chargeAmount = $charge->amount;
        $this->chargeDueDate = $charge->due_date->toDateString();
        $this->resetValidation();

        $this->modal('charge-form')->show();
    }

    public function saveCharge(UpdateCharge $action): void
    {
        Gate::authorize('update', $this->client);

        $charge = $this->findCharge($this->editingChargeId ?? 0);

        $validated = $this->validate([
            'chargeConcept' => ['nullable', 'string', 'max:255'],
            'chargeAmount' => ['required', 'numeric', 'min:0'],
            'chargeDueDate' => ['required', 'date'],
        ]);

        $action->handle($charge, [
            'concept' => $validated['chargeConcept'],
            'amount' => $validated['chargeAmount'],
            'due_date' => $validated['chargeDueDate'],
        ]);

        unset($this->charges);

        $this->modal('charge-form')->close();

        Flux::toast(variant: 'success', text: __('Cobro actualizado.'));
    }

    public function closeChargeModal(): void
    {
        $this->modal('charge-form')->close();
    }

    public function openPaymentsModal(int $chargeId): void
    {
        Gate::authorize('update', $this->client);

        $charge = $this->findCharge($chargeId);

        $this->payingChargeId = $charge->id;
        $this->resetPaymentForm($charge);
        $this->resetValidation();

        $this->modal('charge-payments')->show();
    }

    public function savePayment(RecordChargePayment $action): void
    {
        Gate::authorize('update', $this->client);

        $charge = $this->findCharge($this->payingChargeId ?? 0);

        $validated = $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentPaidOn' => ['required', 'date'],
            'paymentMethod' => ['nullable', 'string', 'max:255'],
            'paymentAccount' => ['nullable', 'string', 'max:255'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentInvoiceReference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle($charge, [
            'amount' => $validated['paymentAmount'],
            'paid_on' => $validated['paymentPaidOn'],
            'method' => $validated['paymentMethod'],
            'account' => $validated['paymentAccount'],
            'reference' => $validated['paymentReference'],
            'invoice_reference' => $validated['paymentInvoiceReference'],
        ]);

        unset($this->charges, $this->payingCharge);

        $this->resetPaymentForm($charge->refresh());

        Flux::toast(variant: 'success', text: __('Abono registrado.'));
    }

    public function deletePayment(int $paymentId, DeleteChargePayment $action): void
    {
        Gate::authorize('update', $this->client);

        $charge = $this->findCharge($this->payingChargeId ?? 0);

        $action->handle(ChargePayment::where('charge_id', $charge->id)->findOrFail($paymentId));

        unset($this->charges, $this->payingCharge);

        $this->resetPaymentForm($charge->refresh());

        Flux::toast(variant: 'success', text: __('Abono eliminado.'));
    }

    public function closePaymentsModal(): void
    {
        $this->payingChargeId = null;

        $this->modal('charge-payments')->close();
    }

    /**
     * @return Builder<Charge>
     */
    private function chargesQuery(): Builder
    {
        return Charge::query()
            ->whereHas('service', fn ($query) => $query
                ->where('client_id', $this->client->id)
                ->when($this->project, fn ($q) => $q->where('project_id', $this->project->id)));
    }

    /**
     * Los cobros se buscan siempre dentro del alcance del panel: el id llega
     * del navegador y sin este filtro se podría editar el de otro cliente.
     */
    private function findCharge(int $chargeId): Charge
    {
        return $this->chargesQuery()->findOrFail($chargeId);
    }

    private function resetPaymentForm(Charge $charge): void
    {
        $this->paymentAmount = (string) $charge->remainingAmount();
        $this->paymentPaidOn = today()->toDateString();
        $this->paymentMethod = null;
        $this->paymentAccount = null;
        $this->paymentReference = null;
        $this->paymentInvoiceReference = null;
    }

    public function render(): View
    {
        return view('livewire.charges-panel');
    }
}
