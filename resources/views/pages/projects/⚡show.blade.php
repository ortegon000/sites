<?php

use App\Actions\Charges\DeleteChargePayment;
use App\Actions\Charges\MarkChargeAsPaid;
use App\Actions\Charges\RecordChargePayment;
use App\Actions\Charges\UpdateCharge;
use App\Actions\Services\CancelService;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Actions\Services\DeleteService;
use App\Enums\AgencyStatus;
use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Charge;
use App\Models\ChargePayment;
use App\Models\Project;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public string $serviceName = '';

    public ?string $serviceDescription = null;

    public string $serviceCategory = ServiceCategory::Other->value;

    public ?int $serviceDomainId = null;

    public string $billingFrequency = '';

    public string $amount = '';

    public string $currency = 'MXN';

    public string $serviceStatus = '';

    public ?string $startsOn = null;

    public ?int $installmentsCount = null;

    public ?int $userIdToAssign = null;

    public ?int $agencyIdToAssign = null;

    public ?string $agencyNotes = null;

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

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
        $this->currency = $project->client->currency;
    }

    /**
     * @return array<int, ServiceBillingFrequency>
     */
    #[Computed]
    public function billingFrequencyOptions(): array
    {
        return ServiceBillingFrequency::cases();
    }

    /**
     * @return array<int, ServiceStatus>
     */
    /**
     * @return array<int, ServiceCategory>
     */
    #[Computed]
    public function serviceCategoryOptions(): array
    {
        return ServiceCategory::cases();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Domain>
     */
    #[Computed]
    public function projectDomains(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->domains()->orderBy('name')->get();
    }

    #[Computed]
    public function serviceStatusOptions(): array
    {
        return ServiceStatus::cases();
    }

    #[Computed]
    public function assignableUsers()
    {
        return User::query()
            ->whereIn('role', [UserRole::Staff, UserRole::Collaborator])
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($this->project->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function assignableAgencies()
    {
        return Agency::query()
            ->where('status', AgencyStatus::Activa)
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($this->project->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function charges()
    {
        return Charge::query()
            ->whereHas('service', fn ($query) => $query->where('project_id', $this->project->id))
            ->with(['service', 'payments'])
            ->orderBy('due_date')
            ->get();
    }

    #[Computed]
    public function payingCharge(): ?Charge
    {
        if ($this->payingChargeId === null) {
            return null;
        }

        return Charge::with('service')
            ->with(['payments' => fn ($query) => $query->orderBy('paid_on')->orderBy('id')])
            ->find($this->payingChargeId);
    }

    public function openServiceModal(): void
    {
        Gate::authorize('update', $this->project);

        $this->reset(['serviceName', 'serviceDescription', 'billingFrequency', 'amount', 'installmentsCount', 'serviceDomainId']);
        $this->serviceCategory = ServiceCategory::Other->value;
        $this->currency = $this->project->client->currency;
        $this->serviceStatus = ServiceStatus::Activo->value;
        $this->startsOn = today()->toDateString();
        $this->resetValidation();

        $this->modal('service-form')->show();
    }

    public function saveService(CreateServiceWithSchedule $action): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'serviceName' => ['required', 'string', 'max:255'],
            'serviceDescription' => ['nullable', 'string', 'max:2000'],
            'serviceCategory' => ['required', Rule::enum(ServiceCategory::class)],
            'serviceDomainId' => ['nullable', Rule::exists('domains', 'id')->where('project_id', $this->project->id)],
            'billingFrequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'serviceStatus' => ['required', Rule::enum(ServiceStatus::class)],
            'startsOn' => ['required', 'date'],
            'installmentsCount' => ['required_if:billingFrequency,'.ServiceBillingFrequency::Installment->value, 'nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $action->handle($this->project, [
            'name' => $validated['serviceName'],
            'description' => $validated['serviceDescription'],
            'category' => ServiceCategory::from($validated['serviceCategory']),
            'domain_id' => $validated['serviceDomainId'],
            'billing_frequency' => ServiceBillingFrequency::from($validated['billingFrequency']),
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => ServiceStatus::from($validated['serviceStatus']),
            'starts_on' => $validated['startsOn'],
            'installments_count' => $validated['billingFrequency'] === ServiceBillingFrequency::Installment->value
                ? $validated['installmentsCount']
                : null,
        ]);

        $this->modal('service-form')->close();

        Flux::toast(variant: 'success', text: __('Servicio creado.'));
    }

    public function deleteService(int $serviceId, DeleteService $action): void
    {
        Gate::authorize('update', $this->project);

        $service = $this->project->services()->findOrFail($serviceId);

        if (! $service->canBeDeleted()) {
            Flux::toast(variant: 'danger', text: __('Este servicio ya tiene cobros pagados. Cancélalo para detenerlo sin borrar el historial.'));

            return;
        }

        $action->handle($service);

        Flux::toast(variant: 'success', text: __('Servicio eliminado.'));
    }

    public function cancelService(int $serviceId, CancelService $action): void
    {
        Gate::authorize('update', $this->project);

        $action->handle($this->project->services()->findOrFail($serviceId));

        Flux::toast(variant: 'success', text: __('Servicio cancelado.'));
    }

    public function closeServiceModal(): void
    {
        $this->modal('service-form')->close();
    }

    public function assignUser(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'userIdToAssign' => ['required', 'exists:users,id'],
        ]);

        $this->project->users()->syncWithoutDetaching([$this->userIdToAssign]);

        $this->userIdToAssign = null;

        Flux::toast(variant: 'success', text: __('Usuario asignado.'));
    }

    public function unassignUser(int $userId): void
    {
        Gate::authorize('update', $this->project);

        $this->project->users()->detach($userId);

        Flux::toast(variant: 'success', text: __('Usuario removido del proyecto.'));
    }

    public function assignAgency(): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'agencyIdToAssign' => ['required', 'exists:agencies,id'],
            'agencyNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->project->agencies()->syncWithoutDetaching([
            $validated['agencyIdToAssign'] => ['notes' => $validated['agencyNotes']],
        ]);

        $this->reset(['agencyIdToAssign', 'agencyNotes']);

        Flux::toast(variant: 'success', text: __('Agencia asociada.'));
    }

    public function unassignAgency(int $agencyId): void
    {
        Gate::authorize('update', $this->project);

        $this->project->agencies()->detach($agencyId);

        Flux::toast(variant: 'success', text: __('Agencia removida del proyecto.'));
    }

    public function markChargeAsPaid(int $chargeId, MarkChargeAsPaid $action): void
    {
        Gate::authorize('update', $this->project);

        $action->handle($this->findCharge($chargeId));

        Flux::toast(variant: 'success', text: __('Cobro marcado como pagado.'));
    }

    public function openChargeModal(int $chargeId): void
    {
        Gate::authorize('update', $this->project);

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
        Gate::authorize('update', $this->project);

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
        Gate::authorize('update', $this->project);

        $charge = $this->findCharge($chargeId);

        $this->payingChargeId = $charge->id;
        $this->resetPaymentForm($charge);
        $this->resetValidation();

        $this->modal('charge-payments')->show();
    }

    public function savePayment(RecordChargePayment $action): void
    {
        Gate::authorize('update', $this->project);

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
        Gate::authorize('update', $this->project);

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
     * Los cobros se buscan siempre dentro del proyecto abierto: el id llega del
     * navegador y sin este filtro se podría editar el cobro de otro cliente.
     */
    private function findCharge(int $chargeId): Charge
    {
        return Charge::whereHas('service', fn ($query) => $query->where('project_id', $this->project->id))
            ->findOrFail($chargeId);
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

    public function render()
    {
        return $this->view()->title($this->project->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
            <flux:text class="text-zinc-400">{{ $project->client->name }}</flux:text>
        </div>

        <flux:badge size="lg">{{ $project->status->label() }}</flux:badge>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="flex flex-col gap-6 md:col-span-1">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Datos generales') }}</flux:heading>

                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Tipo') }}</span>
                    <span>
                        {{ $project->type->label() }}
                        @if ($project->includes_email)
                            <span class="text-xs text-zinc-400">· {{ __('incluye correo') }}</span>
                        @endif
                    </span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Descripción') }}</span>
                    <span>{{ $project->description ?? '—' }}</span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Fecha de inicio') }}</span>
                    <span>{{ $project->started_at?->format('d/m/Y') ?? '—' }}</span>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Equipo asignado') }}</flux:heading>

                @can('update', $project)
                    <form wire:submit="assignUser" class="flex gap-2">
                        <flux:select wire:model="userIdToAssign" class="flex-1">
                            <flux:select.option value="">{{ __('Selecciona un usuario') }}</flux:select.option>
                            @foreach ($this->assignableUsers as $user)
                                <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                    </form>

                    <flux:separator />
                @endcan

                <div class="flex flex-col gap-2">
                    @forelse ($project->users as $user)
                        <div wire:key="assigned-user-{{ $user->id }}" class="flex items-center justify-between text-sm">
                            <span>{{ $user->name }}</span>
                            @can('update', $project)
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unassignUser({{ $user->id }})" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin usuarios asignados.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>

            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Agencias colaboradoras') }}</flux:heading>

                    @can('update', $project)
                        <form wire:submit="assignAgency" class="flex flex-col gap-2">
                            <flux:select wire:model="agencyIdToAssign">
                                <flux:select.option value="">{{ __('Selecciona una agencia') }}</flux:select.option>
                                @foreach ($this->assignableAgencies as $agency)
                                    <flux:select.option value="{{ $agency->id }}">{{ $agency->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:textarea wire:model="agencyNotes" :placeholder="__('Notas (opcional)')" rows="2" />

                            <div class="flex justify-end">
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                            </div>
                        </form>

                        <flux:separator />
                    @endcan

                    <div class="flex flex-col gap-3">
                        @forelse ($project->agencies as $agency)
                            <div wire:key="assigned-agency-{{ $agency->id }}" class="flex flex-col gap-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ $agency->name }}</span>
                                    @can('update', $project)
                                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unassignAgency({{ $agency->id }})" />
                                    @endcan
                                </div>
                                <flux:badge size="sm" :color="$agency->billing_target === \App\Enums\AgencyBillingTarget::Agency ? 'blue' : 'zinc'">
                                    {{ __('Se factura :target', ['target' => mb_strtolower($agency->billing_target->label())]) }}
                                </flux:badge>
                                @if ($agency->pivot->notes)
                                    <span class="text-xs text-zinc-400">{{ $agency->pivot->notes }}</span>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Sin agencias asociadas.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>

            @endif
        </div>

        <div class="flex flex-col gap-6 md:col-span-2">
            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Servicios') }}</flux:heading>

                    @can('update', $project)
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="openServiceModal">
                            {{ __('Agregar servicio') }}
                        </flux:button>
                    @endcan
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                        <flux:table.column>{{ __('Facturación') }}</flux:table.column>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <flux:table.column>{{ __('Monto') }}</flux:table.column>
                        @endif
                        <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                        @can('update', $project)
                            <flux:table.column></flux:table.column>
                        @endcan
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($project->services as $service)
                            <flux:table.row wire:key="service-{{ $service->id }}">
                                <flux:table.cell>{{ $service->name }}</flux:table.cell>
                                <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                                @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                                    <flux:table.cell>{{ $service->amount }} {{ $service->currency }}</flux:table.cell>
                                @endif
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                                </flux:table.cell>
                                @can('update', $project)
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-2">
                                            @if ($service->status !== \App\Enums\ServiceStatus::Cancelado)
                                                <flux:button size="xs" variant="ghost" icon="no-symbol"
                                                    :tooltip="__('Cancelar servicio')"
                                                    wire:click="cancelService({{ $service->id }})"
                                                    wire:confirm="{{ __('¿Cancelar este servicio? Dejará de generar cobros y conservará los que ya tiene.') }}" />
                                            @endif

                                            <flux:button size="xs" variant="ghost" icon="trash"
                                                :tooltip="__('Eliminar servicio')"
                                                wire:click="deleteService({{ $service->id }})"
                                                wire:confirm="{{ __('¿Eliminar este servicio? Se borrarán también sus cobros pendientes y sus cuotas.') }}" />
                                        </div>
                                    </flux:table.cell>
                                @endcan
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-zinc-400">
                                    {{ __('Sin servicios todavía.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Cobros') }}</flux:heading>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Concepto') }}</flux:table.column>
                            <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                            <flux:table.column>{{ __('Monto') }}</flux:table.column>
                            <flux:table.column>{{ __('Restante') }}</flux:table.column>
                            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->charges as $charge)
                                <flux:table.row wire:key="charge-{{ $charge->id }}">
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <span>{{ $charge->conceptLabel() }}</span>
                                            @if ($charge->concept)
                                                <span class="text-xs text-zinc-400">{{ $charge->service->name }}</span>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <span>{{ number_format($charge->remainingAmount(), 2) }}</span>
                                            @if ($charge->payments->isNotEmpty())
                                                <span class="text-xs text-zinc-400">
                                                    {{ __('abonado :amount', ['amount' => number_format($charge->paidAmount(), 2)]) }}
                                                </span>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$charge->status->color()">
                                            {{ $charge->status->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @can('update', $project)
                                            <div class="flex justify-end gap-2">
                                                <flux:button size="xs" variant="ghost" icon="pencil"
                                                    :tooltip="__('Editar cobro')"
                                                    wire:click="openChargeModal({{ $charge->id }})" />

                                                <flux:button size="xs" variant="ghost" icon="banknotes"
                                                    :tooltip="__('Abonos')"
                                                    wire:click="openPaymentsModal({{ $charge->id }})" />

                                                @if ($charge->status !== \App\Enums\ChargeStatus::Pagado)
                                                    <flux:button size="xs" variant="ghost" icon="check"
                                                        :tooltip="__('Marcar pagado')"
                                                        wire:click="markChargeAsPaid({{ $charge->id }})" />
                                                @endif
                                            </div>
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="text-center text-zinc-400">
                                        {{ __('Sin cobros todavía.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>

                <livewire:domains-panel :client="$project->client" :project="$project" :key="'domains-panel-'.$project->id" />

                <livewire:project-campaigns :project="$project" :key="'project-campaigns-'.$project->id" />
            @endif
        </div>
    </div>

    <flux:modal name="charge-form" class="md:w-96">
        <form wire:submit="saveCharge" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Editar cobro') }}</flux:heading>

            <flux:input wire:model="chargeConcept" :label="__('Concepto')"
                :description="__('Si lo dejas vacío se usa el nombre del servicio.')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="chargeAmount" type="number" step="0.01" :label="__('Monto')" required />
                <flux:input wire:model="chargeDueDate" type="date" :label="__('Vencimiento')" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeChargeModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="charge-payments" class="md:w-[36rem]" wire:close="closePaymentsModal">
        @if ($this->payingCharge)
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Abonos') }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $this->payingCharge->conceptLabel() }}</flux:text>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Monto') }}</span>
                        <span>{{ number_format((float) $this->payingCharge->amount, 2) }} {{ $this->payingCharge->currency }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Abonado') }}</span>
                        <span>{{ number_format($this->payingCharge->paidAmount(), 2) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-zinc-400">{{ __('Restante') }}</span>
                        <span>{{ number_format($this->payingCharge->remainingAmount(), 2) }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse ($this->payingCharge->payments as $payment)
                        <div wire:key="payment-{{ $payment->id }}" class="flex items-start justify-between gap-4 border-b border-zinc-100 pb-2 text-sm last:border-0 dark:border-zinc-700">
                            <div class="flex flex-col">
                                <span>{{ number_format((float) $payment->amount, 2) }} · {{ $payment->paid_on->format('d/m/Y') }}</span>
                                <span class="text-xs text-zinc-400">
                                    {{ collect([$payment->method, $payment->account, $payment->reference, $payment->invoice_reference ? __('Folio :folio', ['folio' => $payment->invoice_reference]) : null])->filter()->join(' · ') ?: '—' }}
                                </span>
                            </div>
                            @can('update', $project)
                                <flux:button size="xs" variant="ghost" icon="trash"
                                    wire:click="deletePayment({{ $payment->id }})"
                                    wire:confirm="{{ __('¿Eliminar este abono?') }}" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin abonos todavía.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $project)
                    <flux:separator />

                    @if ($this->payingCharge->remainingAmount() <= 0)
                        <div class="flex items-center justify-between">
                            <flux:text class="text-zinc-400">{{ __('Este cobro ya está cubierto.') }}</flux:text>
                            <flux:button variant="ghost" wire:click="closePaymentsModal">{{ __('Cerrar') }}</flux:button>
                        </div>
                    @else
                    <form wire:submit="savePayment" class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentAmount" type="number" step="0.01" :label="__('Monto del abono')" required />
                            <flux:input wire:model="paymentPaidOn" type="date" :label="__('Fecha de pago')" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentMethod" :label="__('Método')" :placeholder="__('Transferencia, efectivo...')" />
                            <flux:input wire:model="paymentAccount" :label="__('Cuenta')" :placeholder="__('Banco o cuenta que recibió')" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="paymentReference" :label="__('Comprobante')" />
                            <flux:input wire:model="paymentInvoiceReference" :label="__('Folio de factura')" />
                        </div>

                        <div class="flex justify-end gap-2">
                            <flux:button variant="ghost" wire:click="closePaymentsModal">{{ __('Cerrar') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Registrar abono') }}</flux:button>
                        </div>
                    </form>
                    @endif
                @endcan
            </div>
        @endif
    </flux:modal>

    <flux:modal name="service-form" class="md:w-96">
        <form wire:submit="saveService" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Nuevo servicio') }}</flux:heading>

            <flux:input wire:model="serviceName" :label="__('Nombre')" required autofocus />
            <flux:textarea wire:model="serviceDescription" :label="__('Descripción')" rows="2" />

            <flux:select wire:model.live="serviceCategory" :label="__('Categoría')">
                @foreach ($this->serviceCategoryOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->projectDomains->isNotEmpty() && \App\Enums\ServiceCategory::from($serviceCategory ?: 'other')->belongsToDomain())
                <flux:select wire:model="serviceDomainId" :label="__('Dominio')">
                    <flux:select.option value="">{{ __('Sin dominio específico') }}</flux:select.option>
                    @foreach ($this->projectDomains as $projectDomain)
                        <flux:select.option value="{{ $projectDomain->id }}">{{ $projectDomain->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="billingFrequency" :label="__('Facturación')">
                <flux:select.option value="">{{ __('Selecciona una opción') }}</flux:select.option>
                @foreach ($this->billingFrequencyOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="amount" type="number" step="0.01" :label="__('Monto por cobro')" />
                <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />
            </div>

            @if ($billingFrequency === \App\Enums\ServiceBillingFrequency::Installment->value)
                <flux:input wire:model="installmentsCount" type="number" min="1" max="60" :label="__('Número de pagos')" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="startsOn" :label="__('Fecha de inicio')" />

                <flux:select wire:model="serviceStatus" :label="__('Estatus')">
                    @foreach ($this->serviceStatusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeServiceModal">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
