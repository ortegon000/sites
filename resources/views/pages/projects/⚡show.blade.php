<?php

use App\Actions\Charges\MarkChargeAsPaid;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Enums\AgencyBillingDirection;
use App\Enums\AgencyStatus;
use App\Enums\ChargeStatus;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Charge;
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

    public string $billingFrequency = '';

    public string $amount = '';

    public string $currency = 'MXN';

    public string $serviceStatus = '';

    public ?string $startsOn = null;

    public ?int $installmentsCount = null;

    public ?int $userIdToAssign = null;

    public ?int $agencyIdToAssign = null;

    public string $agencyBillingDirection = '';

    public ?string $agencyNotes = null;

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

    /**
     * @return array<int, AgencyBillingDirection>
     */
    #[Computed]
    public function billingDirectionOptions(): array
    {
        return AgencyBillingDirection::cases();
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
            ->with('service')
            ->orderBy('due_date')
            ->get();
    }

    public function openServiceModal(): void
    {
        Gate::authorize('update', $this->project);

        $this->reset(['serviceName', 'serviceDescription', 'billingFrequency', 'amount', 'installmentsCount']);
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
            'agencyBillingDirection' => ['required', Rule::enum(AgencyBillingDirection::class)],
            'agencyNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->project->agencies()->syncWithoutDetaching([
            $validated['agencyIdToAssign'] => [
                'billing_direction' => $validated['agencyBillingDirection'],
                'notes' => $validated['agencyNotes'],
            ],
        ]);

        $this->reset(['agencyIdToAssign', 'agencyBillingDirection', 'agencyNotes']);

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

        $charge = Charge::findOrFail($chargeId);

        $action->handle($charge);

        Flux::toast(variant: 'success', text: __('Cobro marcado como pagado.'));
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

                            <flux:select wire:model="agencyBillingDirection">
                                <flux:select.option value="">{{ __('Selecciona la dirección de facturación') }}</flux:select.option>
                                @foreach ($this->billingDirectionOptions as $option)
                                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
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
                                @if ($agency->pivot->billing_direction)
                                    <flux:badge size="sm">{{ \App\Enums\AgencyBillingDirection::from($agency->pivot->billing_direction)->label() }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Heredada del cliente · falta definir facturación') }}</flux:badge>
                                @endif
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
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-zinc-400">
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
                            <flux:table.column>{{ __('Servicio') }}</flux:table.column>
                            <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                            <flux:table.column>{{ __('Monto') }}</flux:table.column>
                            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->charges as $charge)
                                <flux:table.row wire:key="charge-{{ $charge->id }}">
                                    <flux:table.cell>{{ $charge->service->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ $charge->amount }} {{ $charge->currency }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$charge->status === \App\Enums\ChargeStatus::Vencido ? 'red' : ($charge->status === \App\Enums\ChargeStatus::Pagado ? 'green' : 'zinc')">
                                            {{ $charge->status->label() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @can('update', $project)
                                            @if ($charge->status !== \App\Enums\ChargeStatus::Pagado)
                                                <flux:button size="sm" variant="ghost" icon="check" wire:click="markChargeAsPaid({{ $charge->id }})">
                                                    {{ __('Marcar pagado') }}
                                                </flux:button>
                                            @endif
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                                        {{ __('Sin cobros todavía.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:modal name="service-form" class="md:w-96">
        <form wire:submit="saveService" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Nuevo servicio') }}</flux:heading>

            <flux:input wire:model="serviceName" :label="__('Nombre')" required autofocus />
            <flux:textarea wire:model="serviceDescription" :label="__('Descripción')" rows="2" />

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
