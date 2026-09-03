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
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public Client $client;

    public string $note = '';

    public string $status = '';

    public string $newContactName = '';

    public ?string $newContactEmail = null;

    public ?string $newContactPhone = null;

    public ?string $newContactRole = null;

    public bool $addingContact = false;

    /**
     * La pestaña abierta del expediente. Viaja en la URL (?seccion=trabajo)
     * para poder recargar o compartir el enlace de una sección concreta.
     */
    #[Url(as: 'seccion', except: 'bitacora')]
    public string $tab = 'bitacora';

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

            /** La pestaña abierta se lleva al otro URL: quien acaba de ganar un prospecto desde "Trabajo" quiere ver ahí lo que se creó. */
            $this->redirect(route($correctRoute, [
                'client' => $this->client,
                ...($this->tab === array_key_first($this->tabs) ? [] : ['seccion' => $this->tab]),
            ]), navigate: true);
        }
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ClientStatus::forType($this->client->type);
    }

    /**
     * Las secciones del expediente, en el orden en que se muestran, con el
     * icono que las acompaña en la barra de pestañas.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    #[Computed]
    public function tabs(): array
    {
        return [
            'bitacora' => ['label' => __('Bitácora'), 'icon' => 'clipboard-document-list'],
            'trabajo' => ['label' => __('Trabajo'), 'icon' => 'briefcase'],
            'dominios' => ['label' => __('Dominios y licencias'), 'icon' => 'globe-alt'],
            'campanas' => ['label' => __('Campañas'), 'icon' => 'megaphone'],
        ];
    }

    /**
     * La pestaña que realmente se pinta. La llave llega de la URL, así que
     * una escrita a mano cae en la primera sección en vez de dejar la ficha
     * en blanco y sin nada marcado en la barra.
     */
    #[Computed]
    public function activeTab(): string
    {
        return array_key_exists($this->tab, $this->tabs) ? $this->tab : array_key_first($this->tabs);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contact>
     */
    #[Computed]
    public function contacts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->client->contacts()->get();
    }

    public function startAddingContact(): void
    {
        Gate::authorize('update', $this->client);

        $this->addingContact = true;
    }

    public function cancelAddingContact(): void
    {
        $this->reset(['newContactName', 'newContactEmail', 'newContactPhone', 'newContactRole', 'addingContact']);
        $this->resetValidation();
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

        $this->reset(['newContactName', 'newContactEmail', 'newContactPhone', 'newContactRole', 'addingContact']);
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
     * Aceptar una cotización pasa en su propio panel, pero lo que provoca se
     * ve aquí: si quien aceptó era un prospecto queda ganado y la ficha cambia
     * de URL. Sin este aviso había que recargar para enterarse. El proyecto que
     * abre lo recoge la tarjeta de proyectos, que escucha el mismo evento.
     */
    #[On('quote-accepted')]
    public function refreshAfterQuoteAccepted(): void
    {
        $this->client->refresh();
        $this->status = $this->client->status->value;

        $this->redirectToCanonicalRoute();
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

    /**
     * El estatus se cambia con el switch de hasta arriba de la ficha, así que
     * se aplica en cuanto el usuario lo mueve: ya no hay botón de guardar.
     */
    public function updatedStatus(): void
    {
        $this->changeStatus(app(ChangeClientStatus::class));
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

        @can('update', $client)
            <flux:radio.group wire:model.live="status" variant="segmented" class="max-w-full overflow-x-auto">
                @foreach ($this->statusOptions as $option)
                    <flux:radio value="{{ $option->value }}">{{ $option->label() }}</flux:radio>
                @endforeach
            </flux:radio.group>
        @else
            <flux:badge size="lg">{{ $client->status->label() }}</flux:badge>
        @endcan
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
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Contactos') }}</flux:heading>

                    @can('update', $client)
                        @unless ($addingContact)
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="startAddingContact">
                                {{ __('Agregar contacto') }}
                            </flux:button>
                        @endunless
                    @endcan
                </div>

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
                    @if ($addingContact)
                        <flux:separator />

                        <form wire:submit="addContact" class="flex flex-col gap-2">
                            <flux:input wire:model="newContactName" size="sm" :placeholder="__('Nombre')" />
                            <flux:input wire:model="newContactEmail" type="email" size="sm" :placeholder="__('Correo')" />
                            <flux:input wire:model="newContactPhone" size="sm" :placeholder="__('Teléfono')" />
                            <flux:input wire:model="newContactRole" size="sm" :placeholder="__('Cargo (opcional)')" />

                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="cancelAddingContact">
                                    {{ __('Cancelar') }}
                                </flux:button>
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Guardar contacto') }}</flux:button>
                            </div>
                        </form>
                    @endif
                @endcan
            </flux:card>
        </div>

        <div class="flex flex-col gap-4 md:col-span-2">
            <flux:navbar scrollable class="-mt-3 border-b border-zinc-200 dark:border-zinc-700">
                @foreach ($this->tabs as $key => $tabDefinition)
                    <flux:navbar.item
                        wire:key="client-tab-{{ $key }}"
                        :icon="$tabDefinition['icon']"
                        :current="$this->activeTab === $key"
                        wire:click="$set('tab', '{{ $key }}')"
                    >
                        {{ $tabDefinition['label'] }}
                    </flux:navbar.item>
                @endforeach
            </flux:navbar>

            @php($staffCanSeePanels = auth()->user()->isAdmin() || auth()->user()->isStaff())

            @if ($this->activeTab === 'bitacora')
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

                @if ($staffCanSeePanels)
                    <livewire:contracts-panel :client="$client" :key="'contracts-panel-client-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'trabajo')
                @if ($staffCanSeePanels)
                    <livewire:quotes-panel :client="$client" :key="'quotes-panel-client-'.$client->id" />
                @endif

                <livewire:projects-panel :client="$client" :key="'projects-panel-client-'.$client->id" />

                @if ($staffCanSeePanels)
                    <livewire:services-panel :client="$client" :key="'services-panel-client-'.$client->id" />

                    <livewire:charges-panel :client="$client" :key="'charges-panel-client-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'dominios')
                @if ($staffCanSeePanels)
                    <livewire:domains-panel :client="$client" :key="'domains-panel-client-'.$client->id" />

                    <livewire:client-licenses :client="$client" :key="'client-licenses-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'campanas')
                @if ($staffCanSeePanels)
                    <livewire:campaigns-panel :client="$client" :key="'campaigns-panel-client-'.$client->id" />
                @endif
            @endif
        </div>
    </div>

</div>
