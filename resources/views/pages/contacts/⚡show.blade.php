<?php

use App\Models\Contact;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Contact $contact;

    public string $name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $notes = null;

    public function mount(Contact $contact): void
    {
        Gate::authorize('view', $contact);

        $this->contact = $contact;
    }

    /**
     * Las empresas de esta persona, con lo que cuelga de cada una. Esta es la
     * vista que no existía cuando el contacto vivía duplicado dentro de cada
     * cliente: "todo lo de Juan" en una sola pantalla.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client>
     */
    #[Computed]
    public function clients(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->contact->clients()
            ->withCount(['projects', 'domains'])
            ->get();
    }

    public function openEditModal(): void
    {
        Gate::authorize('update', $this->contact);

        $this->name = $this->contact->name;
        $this->email = $this->contact->email;
        $this->phone = $this->contact->phone;
        $this->notes = $this->contact->notes;
        $this->resetValidation();

        $this->modal('contact-form')->show();
    }

    /**
     * Los datos de la persona se editan aquí, que es donde se ve a quién
     * afectan: el cambio se refleja en todas sus empresas.
     */
    public function save(): void
    {
        Gate::authorize('update', $this->contact);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('contacts', 'email')->ignore($this->contact->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->contact->update($validated);

        $this->modal('contact-form')->close();

        Flux::toast(variant: 'success', text: __('Contacto actualizado.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('contact-form')->close();
    }

    public function render()
    {
        return $this->view()->title($this->contact->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Clientes'), 'href' => route('clients.index')], ['label' => $contact->name]]" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $contact->name }}</flux:heading>
            <flux:text class="text-zinc-400">
                {{ $contact->email ?? __('Sin correo') }}
                @if ($contact->phone)
                    · {{ $contact->phone }}
                @endif
            </flux:text>
        </div>

        @can('update', $contact)
            <flux:button size="sm" icon="pencil" wire:click="openEditModal">
                {{ __('Editar') }}
            </flux:button>
        @endcan
    </div>

    @if ($contact->notes)
        <flux:card>
            <flux:text>{{ $contact->notes }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg">{{ __('Empresas') }}</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Editar estos datos cambia a la persona, así que el cambio se ve en todas sus empresas.') }}
            </flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                <flux:table.column>{{ __('Cargo') }}</flux:table.column>
                <flux:table.column>{{ __('Proyectos') }}</flux:table.column>
                <flux:table.column>{{ __('Dominios') }}</flux:table.column>
                <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->clients as $client)
                    <flux:table.row wire:key="contact-client-{{ $client->id }}">
                        <flux:table.cell>
                            <flux:link :href="route($client->type->value === 'prospect' ? 'prospects.show' : 'clients.show', $client)" wire:navigate>
                                {{ $client->name }}
                            </flux:link>
                            @if ($client->pivot->is_primary)
                                <flux:badge size="sm" class="ms-2">{{ __('Principal') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $client->pivot->role ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $client->projects_count }}</flux:table.cell>
                        <flux:table.cell>{{ $client->domains_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $client->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Esta persona ya no está ligada a ninguna empresa.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="contact-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Editar contacto') }}</flux:heading>

            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" :label="__('Nombre')" required autofocus />
                <flux:input wire:model="email" type="email" :label="__('Correo')" />
                <flux:input wire:model="phone" :label="__('Teléfono')" />
                <flux:textarea wire:model="notes" :label="__('Notas')" rows="3" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeFormModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
