<?php

use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Contact $contact;

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

    public function render()
    {
        return $this->view()->title($this->contact->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
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

        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('contacts.index')" wire:navigate>
            {{ __('Contactos') }}
        </flux:button>
    </div>

    @if ($contact->notes)
        <flux:card>
            <flux:text>{{ $contact->notes }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Empresas') }}</flux:heading>

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
                            {{ __('Esta persona todavía no está ligada a ninguna empresa.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
