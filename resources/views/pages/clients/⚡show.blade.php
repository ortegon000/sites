<?php

use App\Actions\Clients\ChangeClientStatus;
use App\Actions\Clients\LinkContactToClient;
use App\Enums\ClientNoteType;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Client $client;

    public string $note = '';

    public string $status = '';

    public string $newContactName = '';

    public ?string $newContactEmail = null;

    public ?string $newContactPhone = null;

    public ?string $newContactRole = null;

    /**
     * The route this component was reached through on the initial page
     * load. Captured once in mount() rather than re-read from request()
     * later, because subsequent Livewire actions are POSTed to Livewire's
     * own update endpoint — request()->route() at that point reflects that
     * internal endpoint, not the page the browser is actually showing.
     */
    public ?string $routeName = null;

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->status = $client->status->value;
        $this->routeName = request()->route()?->getName();

        $this->redirectToCanonicalRoute();
    }

    /**
     * A prospect and a client share this same component, at two different
     * URLs (/prospectos/{id} and /clientes/{id}). If the record's real type
     * doesn't match the URL used to reach it — either because someone typed
     * the "wrong" URL by hand, or because a status change just converted it
     * — send the browser to the URL that matches its current type.
     *
     * Skipped when $routeName is null (e.g. an isolated Livewire::test()
     * call with no real route), since there's nothing to compare against.
     */
    private function redirectToCanonicalRoute(): void
    {
        if ($this->routeName === null) {
            return;
        }

        $correctRoute = $this->client->type === ClientType::Prospect ? 'prospects.show' : 'clients.show';

        if ($this->routeName !== $correctRoute) {
            $this->routeName = $correctRoute;

            $this->redirect(route($correctRoute, $this->client), navigate: true);
        }
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ClientStatus::forType($this->client->type);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contact>
     */
    #[Computed]
    public function contacts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->client->contacts()->get();
    }

    public function addContact(LinkContactToClient $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'newContactName' => ['required', 'string', 'max:255'],
            'newContactEmail' => ['nullable', 'email', 'max:255'],
            'newContactPhone' => ['nullable', 'string', 'max:50'],
            'newContactRole' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle($this->client, [
            'name' => $validated['newContactName'],
            'email' => $validated['newContactEmail'],
            'phone' => $validated['newContactPhone'],
            'role' => $validated['newContactRole'],
        ], isPrimary: $this->contacts->isEmpty());

        $this->reset(['newContactName', 'newContactEmail', 'newContactPhone', 'newContactRole']);
        unset($this->contacts);

        Flux::toast(variant: 'success', text: __('Contacto agregado.'));
    }

    public function makeContactPrimary(int $contactId): void
    {
        Gate::authorize('update', $this->client);

        $this->client->contacts()->newPivotQuery()->update(['is_primary' => false]);
        $this->client->contacts()->updateExistingPivot($contactId, ['is_primary' => true]);

        unset($this->contacts);

        Flux::toast(variant: 'success', text: __('Contacto principal actualizado.'));
    }

    public function detachContact(int $contactId): void
    {
        Gate::authorize('update', $this->client);

        $this->client->contacts()->detach($contactId);

        unset($this->contacts);

        Flux::toast(variant: 'success', text: __('Contacto desvinculado de esta empresa.'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Domain>
     */
    #[Computed]
    public function domains(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->client->domains()
            ->with(['project', 'emailAccounts.provider'])
            ->orderBy('name')
            ->get();
    }

    public function addNote(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->client->notes()->create([
            'user_id' => auth()->id(),
            'type' => ClientNoteType::Note,
            'body' => $validated['note'],
        ]);

        $this->note = '';

        Flux::toast(variant: 'success', text: __('Nota agregada.'));
    }

    public function changeStatus(ChangeClientStatus $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'status' => ['required', \Illuminate\Validation\Rule::enum(ClientStatus::class)],
        ]);

        $this->client = $action->handle($this->client, ClientStatus::from($validated['status']), auth()->user());

        Flux::toast(variant: 'success', text: __('Estatus actualizado.'));

        $this->redirectToCanonicalRoute();
    }

    public function render()
    {
        return $this->view()->title($this->client->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $client->name }}</flux:heading>
            <flux:text class="text-zinc-400">{{ $client->company_name }}</flux:text>
        </div>

        <flux:badge size="lg">{{ $client->status->label() }}</flux:badge>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="flex flex-col gap-6 md:col-span-1">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Datos generales') }}</flux:heading>

                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Agencia') }}</span>
                    <span>{{ $client->agency?->name ?? __('Sin agencia (contacto directo)') }}</span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Fuente') }}</span>
                    <span>{{ $client->source ?? '—' }}</span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Moneda') }}</span>
                    <span>{{ $client->currency }}</span>
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Contactos') }}</flux:heading>

                <div class="flex flex-col gap-3">
                    @forelse ($this->contacts as $contact)
                        <div wire:key="client-contact-{{ $contact->id }}" class="flex items-start justify-between gap-2">
                            <div class="flex flex-col text-sm">
                                <a href="{{ route('contacts.show', $contact) }}" wire:navigate class="font-medium hover:underline">
                                    {{ $contact->name }}
                                </a>
                                <span class="text-xs text-zinc-400">
                                    {{ $contact->email ?? '—' }}
                                    @if ($contact->phone)
                                        · {{ $contact->phone }}
                                    @endif
                                </span>
                                @if ($contact->pivot->role)
                                    <span class="text-xs text-zinc-400">{{ $contact->pivot->role }}</span>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                @if ($contact->pivot->is_primary)
                                    <flux:badge size="sm">{{ __('Principal') }}</flux:badge>
                                @else
                                    <flux:button size="xs" variant="ghost" icon="star"
                                        :tooltip="__('Hacer contacto principal')"
                                        wire:click="makeContactPrimary({{ $contact->id }})" />
                                @endif
                                <flux:button size="xs" variant="ghost" icon="x-mark"
                                    :tooltip="__('Desvincular de esta empresa')"
                                    wire:click="detachContact({{ $contact->id }})"
                                    wire:confirm="{{ __('¿Desvincular este contacto de esta empresa? La persona se conserva y sigue ligada a sus demás empresas.') }}" />
                            </div>
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin contactos todavía.') }}</flux:text>
                    @endforelse
                </div>

                @can('update', $client)
                    <flux:separator />

                    <form wire:submit="addContact" class="flex flex-col gap-2">
                        <flux:input wire:model="newContactName" size="sm" :placeholder="__('Nombre')" />
                        <flux:input wire:model="newContactEmail" type="email" size="sm" :placeholder="__('Correo')" />
                        <flux:input wire:model="newContactPhone" size="sm" :placeholder="__('Teléfono')" />
                        <flux:input wire:model="newContactRole" size="sm" :placeholder="__('Cargo (opcional)')" />
                        <flux:button type="submit" size="sm">{{ __('Agregar contacto') }}</flux:button>
                    </form>
                @endcan
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Cambiar estatus') }}</flux:heading>

                <form wire:submit="changeStatus" class="flex flex-col gap-4">
                    <flux:select wire:model="status">
                        @foreach ($this->statusOptions as $option)
                            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit" variant="primary">{{ __('Actualizar') }}</flux:button>
                </form>
            </flux:card>
        </div>

        <div class="flex flex-col gap-4 md:col-span-2">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Bitácora') }}</flux:heading>

                <form wire:submit="addNote" class="flex flex-col gap-2">
                    <flux:textarea wire:model="note" :placeholder="__('Agregar una nota...')" rows="3" />
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar nota') }}</flux:button>
                    </div>
                </form>

                <flux:separator />

                <div class="flex flex-col gap-4">
                    @forelse ($client->notes as $note)
                        <div wire:key="note-{{ $note->id }}" class="flex flex-col gap-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700">
                            <div class="flex items-center justify-between text-xs text-zinc-400">
                                <span>{{ $note->type->label() }} · {{ $note->author?->name ?? __('Sistema') }}</span>
                                <span>{{ $note->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm">{{ $note->body }}</p>
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin actividad todavía.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>

            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Dominios y correo') }}</flux:heading>

                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Resumen de solo lectura. Los dominios y sus buzones se administran desde el proyecto correspondiente.') }}
                    </flux:text>

                    <div class="flex flex-col gap-3">
                        @forelse ($this->domains as $domain)
                            <div wire:key="client-domain-{{ $domain->id }}" class="flex flex-col gap-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium">{{ $domain->name }}</span>
                                    <flux:badge size="sm">{{ $domain->status->label() }}</flux:badge>
                                </div>

                                @if ($domain->project)
                                    <a href="{{ route('projects.show', $domain->project) }}" wire:navigate class="text-xs text-zinc-400 hover:underline">
                                        {{ $domain->project->name }}
                                    </a>
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('Sin proyecto asociado') }}</span>
                                @endif

                                @if ($domain->managesEmail())
                                    <div class="flex flex-col gap-1 ps-3">
                                        @forelse ($domain->emailAccounts as $emailAccount)
                                            <span wire:key="client-email-account-{{ $emailAccount->id }}" class="text-sm">
                                                {{ $emailAccount->email_address }}
                                                <span class="text-xs text-zinc-400">· {{ $emailAccount->provider->name }}</span>
                                            </span>
                                        @empty
                                            <span class="text-xs text-zinc-400">{{ __('Sin cuentas de correo todavía.') }}</span>
                                        @endforelse
                                    </div>
                                @elseif ($domain->email_notes)
                                    <span class="text-xs text-zinc-400 ps-3">{{ __('Correo') }}: {{ $domain->email_notes }}</span>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Sin dominios todavía.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

</div>
