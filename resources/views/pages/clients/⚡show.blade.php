<?php

use App\Actions\Clients\ChangeClientStatus;
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
                    <span class="text-zinc-400">{{ __('Contacto') }}</span>
                    <span>{{ $client->contact_name ?? '—' }}</span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Correo') }}</span>
                    <span>{{ $client->email ?? '—' }}</span>
                </div>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Teléfono') }}</span>
                    <span>{{ $client->phone ?? '—' }}</span>
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
        </div>
    </div>
</div>
