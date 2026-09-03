<?php

use App\Enums\AgencyBillingTarget;
use App\Enums\AgencyStatus;
use App\Enums\ChargeStatus;
use App\Enums\ProjectStatus;
use App\Models\Agency;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $statusFilter = null;

    public ?string $billingTargetFilter = null;

    public ?int $editingAgencyId = null;

    public string $name = '';

    public ?string $contact_name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public string $billing_target = '';

    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Agency::class);
    }

    /**
     * @return array<int, AgencyStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return AgencyStatus::cases();
    }

    /**
     * @return array<int, AgencyBillingTarget>
     */
    #[Computed]
    public function billingTargetOptions(): array
    {
        return AgencyBillingTarget::cases();
    }

    #[Computed]
    public function agencies()
    {
        return Agency::query()
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('contact_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->billingTargetFilter, fn ($query) => $query->where('billing_target', $this->billingTargetFilter))
            ->withCount([
                'clients',
                'projects as active_projects_count' => fn ($query) => $query->where('status', ProjectStatus::Activo),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Lo cobrado y lo que falta por cobrar de cada agencia de la página, en una
     * sola consulta. La agencia es la primera columna de la hoja del dueño, así
     * que el listado tiene que contestar "cuánto me deben por aquí" sin abrir
     * proyecto por proyecto.
     *
     * @return array<int, object{collected: float, pending: float}>
     */
    #[Computed]
    public function chargeTotals(): array
    {
        $agencyIds = collect($this->agencies->items())->pluck('id');

        if ($agencyIds->isEmpty()) {
            return [];
        }

        $paidPerCharge = DB::table('charge_payments')
            ->selectRaw('charge_id, sum(amount) as paid')
            ->groupBy('charge_id');

        return DB::table('agency_project')
            ->join('projects', 'projects.id', '=', 'agency_project.project_id')
            ->join('services', 'services.project_id', '=', 'projects.id')
            ->join('charges', 'charges.service_id', '=', 'services.id')
            ->leftJoinSub($paidPerCharge, 'payments', 'payments.charge_id', '=', 'charges.id')
            ->whereIn('agency_project.agency_id', $agencyIds)
            ->whereNull('projects.deleted_at')
            ->groupBy('agency_project.agency_id')
            ->selectRaw('agency_project.agency_id')
            ->selectRaw('coalesce(sum(payments.paid), 0) as collected')
            ->selectRaw('coalesce(sum(case when charges.status = ? then 0 else charges.amount - coalesce(payments.paid, 0) end), 0) as pending', [ChargeStatus::Pagado->value])
            ->get()
            ->keyBy('agency_id')
            ->all();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Agency::class);

        $this->reset(['editingAgencyId', 'name', 'contact_name', 'email', 'phone']);
        $this->billing_target = AgencyBillingTarget::Client->value;
        $this->status = AgencyStatus::Activa->value;
        $this->resetValidation();

        $this->modal('agency-form')->show();
    }

    public function openEditModal(int $agencyId): void
    {
        $agency = Agency::findOrFail($agencyId);

        Gate::authorize('update', $agency);

        $this->editingAgencyId = $agency->id;
        $this->name = $agency->name;
        $this->contact_name = $agency->contact_name;
        $this->email = $agency->email;
        $this->phone = $agency->phone;
        $this->billing_target = $agency->billing_target->value;
        $this->status = $agency->status->value;
        $this->resetValidation();

        $this->modal('agency-form')->show();
    }

    public function save(): void
    {
        $agency = $this->editingAgencyId ? Agency::findOrFail($this->editingAgencyId) : null;

        Gate::authorize($agency ? 'update' : 'create', $agency ?? Agency::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_target' => ['required', Rule::enum(AgencyBillingTarget::class)],
            'status' => ['required', Rule::enum(AgencyStatus::class)],
        ]);

        if ($agency) {
            $agency->update($validated);
        } else {
            Agency::create($validated);
        }

        $this->modal('agency-form')->close();

        Flux::toast(variant: 'success', text: $agency ? __('Agencia actualizada.') : __('Agencia creada.'));
    }

    public function delete(int $agencyId): void
    {
        $agency = Agency::findOrFail($agencyId);

        Gate::authorize('delete', $agency);

        $agency->delete();

        Flux::toast(variant: 'success', text: __('Agencia eliminada.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('agency-form')->close();
    }

    public function render()
    {
        return $this->view()->title(__('Agencias colaboradoras'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Agencias colaboradoras') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Nueva') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre, contacto o correo...')" class="max-w-sm" />

        <flux:select wire:model.live="statusFilter" :placeholder="__('Todos los estatus')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los estatus') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="billingTargetFilter" :placeholder="__('Se factura a cualquiera')" class="max-w-xs">
            <flux:select.option value="">{{ __('Se factura a cualquiera') }}</flux:select.option>
            @foreach ($this->billingTargetOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->agencies">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Contacto') }}</flux:table.column>
            <flux:table.column>{{ __('Se factura') }}</flux:table.column>
            <flux:table.column>{{ __('Clientes / proyectos') }}</flux:table.column>
            <flux:table.column>{{ __('Cobrado / por cobrar') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->agencies as $agency)
                <flux:table.row wire:key="agency-{{ $agency->id }}">
                    <flux:table.cell>{{ $agency->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $agency->contact_name ?? '—' }}</span>
                            <span class="text-zinc-400">{{ $agency->email ?? $agency->phone }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$agency->billing_target === \App\Enums\AgencyBillingTarget::Agency ? 'blue' : 'zinc'">
                            {{ $agency->billing_target->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $agency->clients_count }} / {{ $agency->active_projects_count }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @php ($totals = $this->chargeTotals[$agency->id] ?? null)
                        <div class="flex flex-col">
                            <span>{{ number_format((float) ($totals->collected ?? 0), 2) }}</span>
                            <span class="text-xs {{ ($totals->pending ?? 0) > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-zinc-400' }}">
                                {{ __('por cobrar :amount', ['amount' => number_format((float) ($totals->pending ?? 0), 2)]) }}
                            </span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $agency->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEditModal({{ $agency->id }})" />
                            @can('delete', $agency)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $agency->id }})" wire:confirm="{{ __('¿Eliminar esta agencia?') }}" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-400">
                        {{ __('Sin resultados.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="agency-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingAgencyId ? __('Editar') : __('Nueva') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nombre')" required autofocus />
            <flux:input wire:model="contact_name" :label="__('Persona de contacto')" />
            <flux:input wire:model="email" type="email" :label="__('Correo')" />
            <flux:input wire:model="phone" :label="__('Teléfono')" />

            <flux:select wire:model="billing_target" :label="__('Se factura a')"
                :description="__('De dónde viene el trabajo y a quién se le cobra.')">
                @foreach ($this->billingTargetOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Estatus')">
                @foreach ($this->statusOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeFormModal">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
