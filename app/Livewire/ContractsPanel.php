<?php

namespace App\Livewire;

use App\Actions\Contracts\DraftContract;
use App\Actions\Contracts\SignContract;
use App\Enums\ContractStatus;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Los contratos de un cliente.
 *
 * El contrato no se captura: se genera con lo que ya vive en el sistema
 * —servicios, montos, vigencias y entregables— y luego se edita a mano si hace
 * falta. Por eso llega hasta el final: sin las fases anteriores no había de
 * dónde sacarlo.
 */
class ContractsPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public string $contractTitle = '';

    public string $contractStartsOn = '';

    public ?string $contractEndsOn = null;

    /**
     * @var array<int, int>
     */
    public array $selectedServices = [];

    public ?int $editingContractId = null;

    public string $contractBody = '';

    public ?string $contractNotes = null;

    public ?int $signingContractId = null;

    public string $signedBy = '';

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
    }

    /**
     * @return Collection<int, Contract>
     */
    #[Computed]
    public function contracts(): Collection
    {
        return $this->contractsQuery()
            ->with(['services', 'project'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function contractableServices(): Collection
    {
        return $this->client->services()
            ->whereIn('status', [ServiceStatus::Activo, ServiceStatus::Pausado])
            ->when($this->project, fn ($query) => $query->where('project_id', $this->project->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function editingContract(): ?Contract
    {
        return $this->editingContractId === null
            ? null
            : $this->contractsQuery()->find($this->editingContractId);
    }

    public function openDraftModal(): void
    {
        Gate::authorize('update', $this->client);

        $this->contractTitle = __('Contrato de prestación de servicios');
        $this->contractStartsOn = today()->toDateString();
        $this->contractEndsOn = today()->addYear()->toDateString();
        $this->selectedServices = $this->contractableServices()->pluck('id')->all();
        $this->resetValidation();

        $this->modal('contract-draft')->show();
    }

    public function draft(DraftContract $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'contractTitle' => ['required', 'string', 'max:255'],
            'contractStartsOn' => ['required', 'date'],
            'contractEndsOn' => ['nullable', 'date', 'after_or_equal:contractStartsOn'],
            'selectedServices' => ['array'],
            'selectedServices.*' => ['integer'],
        ]);

        $services = $this->contractableServices()
            ->whereIn('id', $validated['selectedServices'])
            ->values();

        $action->handle(
            $this->client,
            $services,
            $validated['contractTitle'],
            $validated['contractStartsOn'],
            $validated['contractEndsOn'],
            $this->project,
        );

        unset($this->contracts);

        $this->modal('contract-draft')->close();

        Flux::toast(variant: 'success', text: __('Contrato generado. Revísalo antes de enviarlo.'));
    }

    public function closeDraftModal(): void
    {
        $this->modal('contract-draft')->close();
    }

    public function openBodyModal(int $contractId): void
    {
        Gate::authorize('view', $this->client);

        $contract = $this->findContract($contractId);

        $this->editingContractId = $contract->id;
        $this->contractBody = $contract->body;
        $this->contractNotes = $contract->notes;
        $this->resetValidation();

        $this->modal('contract-body')->show();
    }

    public function saveBody(): void
    {
        Gate::authorize('update', $this->client);

        $contract = $this->findContract($this->editingContractId ?? 0);

        if (! $contract->isEditable()) {
            Flux::toast(variant: 'danger', text: __('Un contrato firmado ya no se edita: el documento que se firmó es el que vale.'));

            return;
        }

        $validated = $this->validate([
            'contractBody' => ['required', 'string'],
            'contractNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contract->update([
            'body' => $validated['contractBody'],
            'notes' => $validated['contractNotes'],
        ]);

        unset($this->contracts, $this->editingContract);

        $this->modal('contract-body')->close();

        Flux::toast(variant: 'success', text: __('Contrato actualizado.'));
    }

    public function closeBodyModal(): void
    {
        $this->editingContractId = null;

        $this->modal('contract-body')->close();
    }

    public function markSent(int $contractId): void
    {
        Gate::authorize('update', $this->client);

        $this->findContract($contractId)->update([
            'status' => ContractStatus::Enviado,
            'sent_at' => now(),
        ]);

        unset($this->contracts);

        Flux::toast(variant: 'success', text: __('Contrato marcado como enviado.'));
    }

    public function openSignModal(int $contractId): void
    {
        Gate::authorize('update', $this->client);

        $contact = $this->client->primaryContact();

        $this->signingContractId = $this->findContract($contractId)->id;
        $this->signedBy = $contact !== null ? $contact->name : '';
        $this->resetValidation();

        $this->modal('contract-signature')->show();
    }

    public function sign(SignContract $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'signedBy' => ['required', 'string', 'max:255'],
        ]);

        $action->handle($this->findContract($this->signingContractId ?? 0), $validated['signedBy']);

        unset($this->contracts);

        $this->signingContractId = null;

        $this->modal('contract-signature')->close();

        Flux::toast(variant: 'success', text: __('Contrato firmado.'));
    }

    public function closeSignModal(): void
    {
        $this->signingContractId = null;

        $this->modal('contract-signature')->close();
    }

    public function cancel(int $contractId): void
    {
        Gate::authorize('update', $this->client);

        $this->findContract($contractId)->update(['status' => ContractStatus::Cancelado]);

        unset($this->contracts);

        Flux::toast(variant: 'success', text: __('Contrato cancelado.'));
    }

    /**
     * @return Builder<Contract>
     */
    private function contractsQuery(): Builder
    {
        return Contract::query()
            ->where('client_id', $this->client->id)
            ->when($this->project, fn ($query) => $query->where('project_id', $this->project->id));
    }

    private function findContract(int $contractId): Contract
    {
        return $this->contractsQuery()->findOrFail($contractId);
    }

    public function render(): View
    {
        return view('livewire.contracts-panel');
    }
}
