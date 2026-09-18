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

    public ?int $assignedTo = null;

    public string $newContactName = '';

    public ?string $newContactEmail = null;

    public ?string $newContactPhone = null;

    public ?string $newContactRole = null;

    /**
     * La pestaña abierta del expediente. Viaja en la URL (?seccion=trabajo)
     * para poder recargar o compartir el enlace de una sección concreta.
     */
    #[Url(as: 'seccion', except: 'dominios')]
    public string $tab = 'dominios';

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
        $this->assignedTo = $client->assigned_to_user_id;
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    #[Computed]
    public function assignableUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\User::internal()->orderBy('name')->get();
    }

    /**
     * Como el estatus, el responsable se guarda en cuanto se elige.
     */
    public function updatedAssignedTo(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'assignedTo' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->whereIn('role', [\App\Enums\UserRole::Admin->value, \App\Enums\UserRole::Staff->value])],
        ]);

        $this->client->update(['assigned_to_user_id' => $validated['assignedTo']]);
        $this->client->unsetRelation('assignedTo');

        Flux::toast(variant: 'success', text: __('Responsable actualizado.'));
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ClientStatus::forType($this->client->type);
    }

    /**
     * El camino de vuelta: un prospecto y un cliente comparten pantalla pero
     * cuelgan de listados distintos.
     *
     * @return array<int, array{label: string, href?: string}>
     */
    #[Computed]
    public function breadcrumbs(): array
    {
        $isProspect = $this->client->type === ClientType::Prospect;

        return [
            $isProspect
                ? ['label' => __('Prospectos'), 'href' => route('prospects.index')]
                : ['label' => __('Clientes'), 'href' => route('clients.index')],
            ['label' => $this->client->name],
        ];
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
            'dominios' => ['label' => __('Dominios y licencias'), 'icon' => 'globe-alt'],
            'renovaciones' => ['label' => __('Renovaciones'), 'icon' => 'arrow-path'],
            'trabajo' => ['label' => __('Trabajo'), 'icon' => 'briefcase'],
            'cobros' => ['label' => __('Cobros'), 'icon' => 'banknotes'],
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

        $this->resetValidation();
        $this->modal('add-contact')->show();
    }

    public function cancelAddingContact(): void
    {
        $this->reset(['newContactName', 'newContactEmail', 'newContactPhone', 'newContactRole']);
        $this->resetValidation();
        $this->modal('add-contact')->close();
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

        $this->modal('add-contact')->close();

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
        $this->client->loadMissing('notes.author');

        return $this->view()->title($this->client->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="$this->breadcrumbs" />

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
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Datos generales') }}</flux:heading>
                    <flux:badge size="sm" :color="$client->type === \App\Enums\ClientType::Client ? 'green' : 'zinc'">
                        {{ $client->type->label() }}
                    </flux:badge>
                </div>

                <dl class="flex flex-col divide-y divide-zinc-200 text-sm dark:divide-white/10">
                    @php
                        $details = [
                            ['icon' => 'building-office-2', 'label' => __('Agencia'), 'value' => $client->agency?->name, 'empty' => __('Contacto directo')],
                            ['icon' => 'megaphone', 'label' => __('Fuente'), 'value' => $client->source, 'empty' => __('Sin registrar')],
                            ['icon' => 'banknotes', 'label' => __('Moneda'), 'value' => $client->currency, 'empty' => '—'],
                            ['icon' => 'calendar-days', 'label' => $client->won_at ? __('Cliente desde') : __('Registrado'), 'value' => ($client->won_at ?? $client->created_at)?->translatedFormat('j M Y'), 'empty' => '—'],
                        ];
                    @endphp

                    <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                        <dt class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="user-circle" variant="outline" class="size-4" />
                            {{ __('Responsable') }}
                        </dt>
                        <dd class="text-right font-medium">
                            @can('update', $client)
                                <flux:select wire:model.live="assignedTo" size="sm" class="w-44" :aria-label="__('Responsable')">
                                    <flux:select.option value="">{{ __('Sin asignar') }}</flux:select.option>
                                    @foreach ($this->assignableUsers as $user)
                                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <span @class(['font-normal text-zinc-400' => ! $client->assignedTo])>{{ $client->assignedTo?->name ?? __('Sin asignar') }}</span>
                            @endcan
                        </dd>
                    </div>

                    @foreach ($details as $detail)
                        <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                            <dt class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <flux:icon :name="$detail['icon']" variant="outline" class="size-4" />
                                {{ $detail['label'] }}
                            </dt>
                            <dd @class(['text-right font-medium', 'font-normal text-zinc-400' => blank($detail['value'])])>
                                {{ filled($detail['value']) ? $detail['value'] : $detail['empty'] }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Contactos') }}</flux:heading>
                        @if ($this->contacts->isNotEmpty())
                            <flux:badge size="sm" color="zinc">{{ $this->contacts->count() }}</flux:badge>
                        @endif
                    </div>

                    @can('update', $client)
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="startAddingContact">
                            {{ __('Agregar') }}
                        </flux:button>
                    @endcan
                </div>

                <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                    @forelse ($this->contacts as $contact)
                        <div wire:key="client-contact-{{ $contact->id }}" class="group flex items-start gap-2 py-4 first:pt-0 last:pb-0">

                            <div class="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                                <div class="flex flex-wrap items-center gap-x-2">
                                    <a href="{{ route('contacts.show', $contact) }}" wire:navigate class="font-medium hover:underline">
                                        {{ $contact->name }}
                                    </a>
                                    @if ($contact->pivot->is_primary)
                                        <flux:badge size="sm" color="amber" icon="star" inset="top bottom">{{ __('Principal') }}</flux:badge>
                                    @endif
                                </div>

                                @if ($contact->pivot->role)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contact->pivot->role }}</span>
                                @endif

                                <div class="mt-1.5 flex flex-col gap-1.5">
                                @if ($contact->email)
                                    <a href="mailto:{{ $contact->email }}" class="flex items-center gap-2 truncate text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                        <flux:icon name="envelope" variant="micro" class="shrink-0" />
                                        <span class="truncate">{{ $contact->email }}</span>
                                    </a>
                                @endif
                                @if ($contact->phone)
                                    <a href="tel:{{ $contact->phone }}" class="flex items-center gap-2 text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                        <flux:icon name="phone" variant="micro" class="shrink-0" />
                                        {{ $contact->phone }}
                                    </a>
                                @endif
                                </div>
                            </div>

                            @can('update', $client)
                                <div class="flex shrink-0 items-center gap-0.5 sm:opacity-0 sm:transition-opacity sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
                                    @unless ($contact->pivot->is_primary)
                                        <flux:button size="xs" variant="ghost" icon="star"
                                            :tooltip="__('Hacer contacto principal')"
                                            wire:click="makeContactPrimary({{ $contact->id }})" />
                                    @endunless
                                    <flux:button size="xs" variant="ghost" icon="x-mark"
                                        :tooltip="__('Desvincular de esta empresa')"
                                        wire:click="detachContact({{ $contact->id }})"
                                        wire:confirm="{{ __('¿Desvincular este contacto de esta empresa? La persona se conserva y sigue ligada a sus demás empresas.') }}" />
                                </div>
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin contactos todavía.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Bitácora') }}</flux:heading>

                <form wire:submit="addNote" class="flex flex-col gap-2">
                    <flux:textarea wire:model="note" :placeholder="__('Escribe una nota sobre este cliente...')" rows="2" resize="none" />
                    <flux:error name="note" />
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane">{{ __('Agregar nota') }}</flux:button>
                    </div>
                </form>

                @if ($client->notes->isEmpty())
                    <flux:text class="text-zinc-400">{{ __('Sin actividad todavía.') }}</flux:text>
                @else
                    <ol class="relative flex flex-col gap-5 border-t border-zinc-200 pt-5 dark:border-white/10">
                        @foreach ($client->notes as $note)
                            @php($isEvent = $note->type === \App\Enums\ClientNoteType::StatusChange)
                            <li wire:key="note-{{ $note->id }}" class="relative flex gap-3">
                                @unless ($loop->last)
                                    <span class="absolute top-7 -bottom-5 left-3 w-px bg-zinc-200 dark:bg-white/10" aria-hidden="true"></span>
                                @endunless

                                <span @class([
                                    'relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full',
                                    'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400' => $isEvent,
                                    'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300' => ! $isEvent,
                                ])>
                                    <flux:icon :name="$note->type->icon()" variant="micro" />
                                </span>

                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>
                                            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $note->author?->name ?? __('Sistema') }}</span>
                                            @unless ($note->type === \App\Enums\ClientNoteType::Note)
                                                · {{ $note->type->label() }}
                                            @endunless
                                        </span>
                                        <time datetime="{{ $note->created_at->toIso8601String() }}" title="{{ $note->created_at->translatedFormat('j M Y, H:i') }}">
                                            {{ $note->created_at->diffForHumans() }}
                                        </time>
                                    </div>
                                    <p @class(['text-sm whitespace-pre-line', 'text-zinc-500 dark:text-zinc-400' => $isEvent])>{{ $note->body }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
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

            @if ($this->activeTab === 'trabajo')
                @if ($staffCanSeePanels)
                    <livewire:quotes-panel :client="$client" :key="'quotes-panel-client-'.$client->id" />
                @endif

                <livewire:projects-panel :client="$client" :key="'projects-panel-client-'.$client->id" />

                @if ($staffCanSeePanels)
                    <livewire:services-panel :client="$client" :key="'services-panel-client-'.$client->id" />

                    <livewire:campaigns-panel :client="$client" :key="'campaigns-panel-client-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'cobros')
                @if ($staffCanSeePanels)
                    <livewire:charges-panel :client="$client" :key="'charges-panel-client-'.$client->id" />

                    <livewire:contracts-panel :client="$client" :key="'contracts-panel-client-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'dominios')
                @if ($staffCanSeePanels)
                    <livewire:domains-panel :client="$client" :key="'domains-panel-client-'.$client->id" />

                    <livewire:client-licenses :client="$client" :key="'client-licenses-'.$client->id" />
                @endif
            @elseif ($this->activeTab === 'renovaciones')
                @if ($staffCanSeePanels)
                    <livewire:renewals-panel :client="$client" :key="'renewals-panel-client-'.$client->id" />
                @endif
            @endif
        </div>
    </div>

    @can('update', $client)
        <flux:modal name="add-contact" class="md:w-96">
            <form wire:submit="addContact" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Agregar contacto') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Se guarda como persona. Si ya existe, se reutiliza y queda ligada también a esta empresa.') }}
                    </flux:text>
                </div>

                <flux:input wire:model="newContactName" :label="__('Nombre')" required autofocus />
                <flux:input wire:model="newContactEmail" type="email" :label="__('Correo')" />
                <flux:input wire:model="newContactPhone" :label="__('Teléfono')" />
                <flux:input wire:model="newContactRole" :label="__('Cargo')" :placeholder="__('Opcional')" />

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="cancelAddingContact">{{ __('Cancelar') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Guardar contacto') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
