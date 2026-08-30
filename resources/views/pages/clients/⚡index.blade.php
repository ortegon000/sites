<?php

use App\Actions\Clients\ChangeClientStatus;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
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

    public ?int $editingClientId = null;

    public string $name = '';

    public ?string $company_name = null;

    public ?string $contact_name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $source = null;

    public string $currency = 'MXN';

    public string $status = '';

    public ClientType $type;

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);

        $this->type = request()->routeIs('prospects.index') ? ClientType::Prospect : ClientType::Client;
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ClientStatus::forType($this->type);
    }

    #[Computed]
    public function clients()
    {
        return Client::query()
            ->where('type', $this->type)
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('company_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Client::class);

        $this->reset(['editingClientId', 'name', 'company_name', 'contact_name', 'email', 'phone', 'source']);
        $this->currency = 'MXN';
        $this->status = $this->statusOptions[0]->value;
        $this->resetValidation();

        $this->modal('client-form')->show();
    }

    public function openEditModal(int $clientId): void
    {
        $client = Client::findOrFail($clientId);

        Gate::authorize('update', $client);

        $this->editingClientId = $client->id;
        $this->name = $client->name;
        $this->company_name = $client->company_name;
        $this->contact_name = $client->contact_name;
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->source = $client->source;
        $this->currency = $client->currency;
        $this->status = $client->status->value;
        $this->resetValidation();

        $this->modal('client-form')->show();
    }

    public function save(ChangeClientStatus $changeClientStatus): void
    {
        $client = $this->editingClientId ? Client::findOrFail($this->editingClientId) : null;

        Gate::authorize($client ? 'update' : 'create', $client ?? Client::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
        ]);

        $status = ClientStatus::from($validated['status']);
        unset($validated['status']);

        if ($client) {
            $client->update($validated);

            if ($status !== $client->status) {
                $changeClientStatus->handle($client, $status, auth()->user());
            }
        } else {
            $validated['type'] = $this->type;
            $validated['status'] = $status;
            $validated['assigned_to_user_id'] = auth()->id();
            Client::create($validated);
        }

        $this->modal('client-form')->close();

        Flux::toast(variant: 'success', text: $client ? __('Cliente actualizado.') : __('Cliente creado.'));
    }

    public function delete(int $clientId): void
    {
        $client = Client::findOrFail($clientId);

        Gate::authorize('delete', $client);

        $client->delete();

        Flux::toast(variant: 'success', text: __('Cliente eliminado.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('client-form')->close();
    }

    public function render()
    {
        return $this->view()->title($this->type->value === 'prospect' ? __('Prospectos') : __('Clientes'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">
            {{ $this->type->value === 'prospect' ? __('Prospectos') : __('Clientes') }}
        </flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Nuevo') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre, empresa o correo...')" class="max-w-sm" />

        <flux:select wire:model.live="statusFilter" :placeholder="__('Todos los estatus')" class="max-w-xs">
            <flux:select.option value="">{{ __('Todos los estatus') }}</flux:select.option>
            @foreach ($this->statusOptions as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->clients">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Empresa') }}</flux:table.column>
            <flux:table.column>{{ __('Contacto') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->clients as $client)
                <flux:table.row wire:key="client-{{ $client->id }}">
                    <flux:table.cell>
                        <flux:link :href="route($this->type->value === 'prospect' ? 'prospects.show' : 'clients.show', $client)" wire:navigate>{{ $client->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $client->company_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $client->email ?? '—' }}</span>
                            <span class="text-zinc-400">{{ $client->phone }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $client->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEditModal({{ $client->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $client->id }})" wire:confirm="{{ __('¿Eliminar este registro?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('Sin resultados.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="client-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingClientId ? __('Editar') : __('Nuevo') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nombre')" required autofocus />
            <flux:input wire:model="company_name" :label="__('Empresa')" />
            <flux:input wire:model="contact_name" :label="__('Persona de contacto')" />
            <flux:input wire:model="email" type="email" :label="__('Correo')" />
            <flux:input wire:model="phone" :label="__('Teléfono')" />
            <flux:input wire:model="source" :label="__('Fuente')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />

                <flux:select wire:model="status" :label="__('Estatus')">
                    @foreach ($this->statusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

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
