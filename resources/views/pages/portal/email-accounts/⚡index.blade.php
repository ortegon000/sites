<?php

use App\Models\EmailAccount;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public function mount(): void
    {
        abort_if(auth()->user()->client_id === null, 403);
    }

    #[Computed]
    public function emailAccounts()
    {
        return EmailAccount::query()
            ->where('client_id', auth()->user()->client_id)
            ->with('provider')
            ->orderBy('email_address')
            ->get();
    }

    public function render()
    {
        return $this->view()->title(__('Mi correo'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <flux:heading size="xl">{{ __('Mi correo') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($this->emailAccounts as $emailAccount)
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
                </div>
            </flux:card>
        @empty
            <flux:text class="text-zinc-400">{{ __('Todavía no tienes cuentas de correo.') }}</flux:text>
        @endforelse
    </div>
</div>
