<?php

use App\Models\Domain;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    /**
     * Passwords the viewer asked to see, keyed by mailbox id. They are only
     * fetched when the button is pressed, so a password never ships with the
     * initial page render.
     *
     * @var array<int, string>
     */
    public array $revealedPasswords = [];

    public function mount(): void
    {
        abort_if(auth()->user()->contact_id === null, 403);
    }

    /**
     * @return Collection<int, Domain>
     */
    #[Computed]
    public function domains(): Collection
    {
        return Domain::query()
            ->whereIn('client_id', auth()->user()->clients()->select('clients.id'))
            ->with('client')
            ->with(['emailAccounts.provider'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Domain $domain) => $domain->managesEmail())
            ->values();
    }

    public function revealPassword(int $emailAccountId): void
    {
        $emailAccount = $this->domains
            ->flatMap->emailAccounts
            ->firstWhere('id', $emailAccountId);

        if ($emailAccount?->password !== null) {
            $this->revealedPasswords[$emailAccountId] = $emailAccount->password;
        }
    }

    public function hidePassword(int $emailAccountId): void
    {
        unset($this->revealedPasswords[$emailAccountId]);
    }

    public function render()
    {
        return $this->view()->title(__('Mi correo'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <flux:heading size="xl">{{ __('Mi correo') }}</flux:heading>

    @forelse ($this->domains as $domain)
        <div wire:key="portal-domain-{{ $domain->id }}" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <flux:heading size="lg">{{ $domain->name }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">{{ $domain->client->name }}</flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($domain->emailAccounts as $emailAccount)
                    @php $settings = $emailAccount->provider->driver()->getConnectionSettings($emailAccount->provider) @endphp

                    <flux:card wire:key="portal-email-account-{{ $emailAccount->id }}" class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <flux:heading size="lg">{{ $emailAccount->email_address }}</flux:heading>
                            <flux:badge size="sm">{{ $emailAccount->status->label() }}</flux:badge>
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <span class="text-zinc-400">{{ __('Servidor IMAP') }}</span>
                            <span>{{ $settings['imap_host'] }}:{{ $settings['imap_port'] }}</span>
                            <span class="text-zinc-400">{{ __('Servidor SMTP') }}</span>
                            <span>{{ $settings['smtp_host'] }}:{{ $settings['smtp_port'] }}</span>

                            @if ($emailAccount->password !== null)
                                <span class="text-zinc-400">{{ __('Contraseña') }}</span>
                                <span class="flex items-center gap-2">
                                    @if (array_key_exists($emailAccount->id, $revealedPasswords))
                                        <span class="font-mono">{{ $revealedPasswords[$emailAccount->id] }}</span>
                                        <flux:button size="xs" variant="ghost" icon="eye-slash"
                                            wire:click="hidePassword({{ $emailAccount->id }})" />
                                    @else
                                        <span class="text-zinc-400">••••••••</span>
                                        <flux:button size="xs" variant="ghost" icon="eye"
                                            wire:click="revealPassword({{ $emailAccount->id }})">
                                            {{ __('Mostrar') }}
                                        </flux:button>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </flux:card>
                @empty
                    <flux:text class="text-zinc-400">{{ __('Este dominio todavía no tiene cuentas de correo.') }}</flux:text>
                @endforelse
            </div>
        </div>
    @empty
        <flux:text class="text-zinc-400">{{ __('Todavía no tienes cuentas de correo.') }}</flux:text>
    @endforelse
</div>
