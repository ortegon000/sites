<?php

use App\Actions\Clients\ChangeClientStatus;
use App\Actions\EmailAccounts\ChangeEmailAccountPassword;
use App\Actions\EmailAccounts\DeleteEmailAccount;
use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\ClientNoteType;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\EmailProviderStatus;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Client $client;

    public string $note = '';

    public string $status = '';

    public ?int $emailProviderIdToAssign = null;

    public string $newEmailAddress = '';

    public string $newEmailPassword = '';

    public ?int $passwordAccountId = null;

    public string $newPassword = '';

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

    #[Computed]
    public function activeEmailProviders()
    {
        return EmailProvider::query()
            ->where('status', EmailProviderStatus::Activo)
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

    public function provisionEmailAccount(ProvisionEmailAccount $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'emailProviderIdToAssign' => ['required', 'exists:email_providers,id'],
            'newEmailAddress' => ['required', 'email', 'max:255', 'unique:email_accounts,email_address'],
            'newEmailPassword' => ['required', 'string', 'min:8'],
        ]);

        $action->handle(
            $this->client,
            EmailProvider::findOrFail($validated['emailProviderIdToAssign']),
            $validated['newEmailAddress'],
            $validated['newEmailPassword'],
        );

        $this->reset(['emailProviderIdToAssign', 'newEmailAddress', 'newEmailPassword']);

        Flux::toast(variant: 'success', text: __('Cuenta de correo creada.'));
    }

    public function deleteEmailAccount(int $emailAccountId, DeleteEmailAccount $action): void
    {
        Gate::authorize('update', $this->client);

        $emailAccount = $this->client->emailAccounts()->findOrFail($emailAccountId);

        $action->handle($emailAccount);

        Flux::toast(variant: 'success', text: __('Cuenta de correo eliminada.'));
    }

    public function openPasswordModal(int $emailAccountId): void
    {
        Gate::authorize('update', $this->client);

        $this->passwordAccountId = $emailAccountId;
        $this->newPassword = '';
        $this->resetValidation();

        $this->modal('email-password-form')->show();
    }

    public function changePassword(ChangeEmailAccountPassword $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $emailAccount = $this->client->emailAccounts()->findOrFail($this->passwordAccountId);

        $action->handle($emailAccount, $validated['newPassword']);

        $this->modal('email-password-form')->close();

        Flux::toast(variant: 'success', text: __('Contraseña actualizada.'));
    }

    public function closePasswordModal(): void
    {
        $this->modal('email-password-form')->close();
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

            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Cuentas de correo') }}</flux:heading>

                    <form wire:submit="provisionEmailAccount" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <flux:select wire:model="emailProviderIdToAssign" :label="__('Proveedor')" class="flex-1">
                            <flux:select.option value="">{{ __('Selecciona un proveedor') }}</flux:select.option>
                            @foreach ($this->activeEmailProviders as $provider)
                                <flux:select.option value="{{ $provider->id }}">{{ $provider->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="newEmailAddress" type="email" :label="__('Correo')" class="flex-1" />

                        <flux:input wire:model="newEmailPassword" type="password" :label="__('Contraseña')" class="flex-1" />

                        <flux:button type="submit" size="sm" variant="primary">{{ __('Crear') }}</flux:button>
                    </form>

                    <flux:separator />

                    <div class="flex flex-col gap-2">
                        @forelse ($client->emailAccounts as $emailAccount)
                            <div wire:key="email-account-{{ $emailAccount->id }}" class="flex items-center justify-between gap-2 text-sm">
                                <div class="flex flex-col">
                                    <span>{{ $emailAccount->email_address }}</span>
                                    <span class="text-xs text-zinc-400">{{ $emailAccount->provider->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm">{{ $emailAccount->status->label() }}</flux:badge>
                                    <flux:button size="xs" variant="ghost" icon="key" wire:click="openPasswordModal({{ $emailAccount->id }})" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteEmailAccount({{ $emailAccount->id }})" wire:confirm="{{ __('¿Eliminar esta cuenta de correo?') }}" />
                                </div>
                            </div>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Sin cuentas de correo todavía.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:modal name="email-password-form" class="md:w-80">
        <form wire:submit="changePassword" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Cambiar contraseña') }}</flux:heading>

            <flux:input wire:model="newPassword" type="password" :label="__('Nueva contraseña')" autofocus />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closePasswordModal">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
