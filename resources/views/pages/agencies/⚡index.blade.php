<?php

use App\Enums\AgencyStatus;
use App\Models\Agency;
use Flux\Flux;
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

    public ?int $editingAgencyId = null;

    public string $name = '';

    public ?string $contact_name = null;

    public ?string $email = null;

    public ?string $phone = null;

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

    #[Computed]
    public function agencies()
    {
        return Agency::query()
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('contact_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Agency::class);

        $this->reset(['editingAgencyId', 'name', 'contact_name', 'email', 'phone']);
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
    </div>

    <flux:table :paginate="$this->agencies">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Contacto') }}</flux:table.column>
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
                    <flux:table.cell colspan="4" class="text-center text-zinc-400">
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
