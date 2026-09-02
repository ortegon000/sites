<?php

use App\Models\Contact;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingContactId = null;

    public string $name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $notes = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Contact::class);
    }

    #[Computed]
    public function contacts()
    {
        return Contact::query()
            ->withCount('clients')
            ->with('clients')
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Contact::class);

        $this->reset(['editingContactId', 'name', 'email', 'phone', 'notes']);
        $this->resetValidation();

        $this->modal('contact-form')->show();
    }

    public function openEditModal(int $contactId): void
    {
        $contact = Contact::findOrFail($contactId);

        Gate::authorize('update', $contact);

        $this->editingContactId = $contact->id;
        $this->name = $contact->name;
        $this->email = $contact->email;
        $this->phone = $contact->phone;
        $this->notes = $contact->notes;
        $this->resetValidation();

        $this->modal('contact-form')->show();
    }

    public function save(): void
    {
        $contact = $this->editingContactId ? Contact::findOrFail($this->editingContactId) : null;

        Gate::authorize($contact ? 'update' : 'create', $contact ?? Contact::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', \Illuminate\Validation\Rule::unique('contacts', 'email')->ignore($this->editingContactId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $isNew = $contact === null;

        if ($contact) {
            $contact->update($validated);
        } else {
            Contact::create($validated);
        }

        unset($this->contacts);

        $this->modal('contact-form')->close();

        Flux::toast(variant: 'success', text: $isNew ? __('Contacto creado.') : __('Contacto actualizado.'));
    }

    public function delete(int $contactId): void
    {
        $contact = Contact::findOrFail($contactId);

        Gate::authorize('delete', $contact);

        $contact->delete();

        unset($this->contacts);

        Flux::toast(variant: 'success', text: __('Contacto eliminado.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('contact-form')->close();
    }

    public function render()
    {
        return $this->view()->title(__('Contactos'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Contactos') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Las personas con las que tratas. Una misma persona puede ser contacto de varias empresas.') }}</flux:text>
        </div>

        @can('create', \App\Models\Contact::class)
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Nuevo') }}
            </flux:button>
        @endcan
    </div>

    @include('partials.clients-nav', ['current' => 'contacts'])

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre, correo o teléfono...')" class="max-w-sm" />

    <flux:table :paginate="$this->contacts">
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Contacto') }}</flux:table.column>
            <flux:table.column>{{ __('Empresas') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->contacts as $contact)
                <flux:table.row wire:key="contact-{{ $contact->id }}">
                    <flux:table.cell>
                        <flux:link :href="route('contacts.show', $contact)" wire:navigate>{{ $contact->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $contact->email ?? '—' }}</span>
                            <span class="text-zinc-400">{{ $contact->phone }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($contact->clients_count === 0)
                            <span class="text-zinc-400">{{ __('Sin empresas') }}</span>
                        @else
                            <div class="flex flex-col">
                                @foreach ($contact->clients->take(3) as $client)
                                    <span class="text-sm">{{ $client->name }}</span>
                                @endforeach
                                @if ($contact->clients_count > 3)
                                    <span class="text-xs text-zinc-400">
                                        {{ __('y :count más', ['count' => $contact->clients_count - 3]) }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            @can('update', $contact)
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEditModal({{ $contact->id }})" />
                            @endcan
                            @can('delete', $contact)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $contact->id }})" wire:confirm="{{ __('¿Eliminar este contacto? Se desvincula de todas sus empresas, que no se tocan.') }}" />
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

    <flux:modal name="contact-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingContactId ? __('Editar') : __('Nuevo') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nombre')" required autofocus />
            <flux:input wire:model="email" type="email" :label="__('Correo')" />
            <flux:input wire:model="phone" :label="__('Teléfono')" />
            <flux:textarea wire:model="notes" :label="__('Notas')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeFormModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
