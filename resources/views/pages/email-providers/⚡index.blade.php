<?php

use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Models\EmailProvider;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $editingProviderId = null;

    public string $name = '';

    public string $driver = '';

    public string $status = '';

    public string $imapHost = '';

    public string $imapPort = '';

    public string $smtpHost = '';

    public string $smtpPort = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', EmailProvider::class);
    }

    /**
     * @return array<int, EmailProviderDriverType>
     */
    #[Computed]
    public function driverOptions(): array
    {
        return EmailProviderDriverType::implemented();
    }

    /**
     * @return array<int, EmailProviderStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return EmailProviderStatus::cases();
    }

    #[Computed]
    public function providers()
    {
        return EmailProvider::query()
            ->withCount('emailAccounts')
            ->orderByDesc('created_at')
            ->get();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', EmailProvider::class);

        $this->reset(['editingProviderId', 'name', 'imapHost', 'imapPort', 'smtpHost', 'smtpPort']);
        $this->driver = EmailProviderDriverType::NullDriver->value;
        $this->status = EmailProviderStatus::Activo->value;
        $this->resetValidation();

        $this->modal('provider-form')->show();
    }

    public function openEditModal(int $providerId): void
    {
        $provider = EmailProvider::findOrFail($providerId);

        Gate::authorize('update', $provider);

        $this->editingProviderId = $provider->id;
        $this->name = $provider->name;
        $this->driver = $provider->driver->value;
        $this->status = $provider->status->value;
        $this->imapHost = $provider->connection_settings['imap_host'] ?? '';
        $this->imapPort = (string) ($provider->connection_settings['imap_port'] ?? '');
        $this->smtpHost = $provider->connection_settings['smtp_host'] ?? '';
        $this->smtpPort = (string) ($provider->connection_settings['smtp_port'] ?? '');
        $this->resetValidation();

        $this->modal('provider-form')->show();
    }

    public function save(): void
    {
        $provider = $this->editingProviderId ? EmailProvider::findOrFail($this->editingProviderId) : null;

        Gate::authorize($provider ? 'update' : 'create', $provider ?? EmailProvider::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::enum(EmailProviderDriverType::class)],
            'status' => ['required', Rule::enum(EmailProviderStatus::class)],
            'imapHost' => ['nullable', 'string', 'max:255'],
            'imapPort' => ['nullable', 'string', 'max:10'],
            'smtpHost' => ['nullable', 'string', 'max:255'],
            'smtpPort' => ['nullable', 'string', 'max:10'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'status' => $validated['status'],
            'connection_settings' => array_filter([
                'imap_host' => $validated['imapHost'],
                'imap_port' => $validated['imapPort'],
                'smtp_host' => $validated['smtpHost'],
                'smtp_port' => $validated['smtpPort'],
            ]) ?: null,
        ];

        if ($provider) {
            $provider->update($attributes);
        } else {
            EmailProvider::create($attributes);
        }

        $this->modal('provider-form')->close();

        Flux::toast(variant: 'success', text: $provider ? __('Proveedor actualizado.') : __('Proveedor creado.'));
    }

    public function delete(int $providerId): void
    {
        $provider = EmailProvider::findOrFail($providerId);

        Gate::authorize('delete', $provider);

        $provider->delete();

        Flux::toast(variant: 'success', text: __('Proveedor eliminado.'));
    }

    public function closeFormModal(): void
    {
        $this->modal('provider-form')->close();
    }

    public function render()
    {
        return $this->view()->title(__('Proveedores de correo'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :items="[['label' => __('Proveedores de correo')]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Proveedores de correo') }}</flux:heading>
            <flux:text class="text-zinc-400">{{ __('Cuentas de proveedor usadas para aprovisionar correos de clientes.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Nuevo') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Driver') }}</flux:table.column>
            <flux:table.column>{{ __('Cuentas') }}</flux:table.column>
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->providers as $provider)
                <flux:table.row wire:key="provider-{{ $provider->id }}">
                    <flux:table.cell>{{ $provider->name }}</flux:table.cell>
                    <flux:table.cell>{{ $provider->driver->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $provider->email_accounts_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $provider->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEditModal({{ $provider->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $provider->id }})" wire:confirm="{{ __('¿Eliminar este proveedor?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-400">
                        {{ __('Sin proveedores todavía.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="provider-form" class="md:w-96">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingProviderId ? __('Editar') : __('Nuevo') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Nombre')" required autofocus />

            <flux:select wire:model="driver" :label="__('Driver')">
                @foreach ($this->driverOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="status" :label="__('Estatus')">
                @foreach ($this->statusOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator />

            <flux:text class="text-xs text-zinc-400">
                {{ __('Datos de conexión que verá el cliente en su portal. Un driver con API real los resuelve solo; captúralos para proveedores manuales.') }}
            </flux:text>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="imapHost" :label="__('Servidor IMAP')" placeholder="imap.ejemplo.com" />
                <flux:input wire:model="imapPort" :label="__('Puerto IMAP')" placeholder="993" />
                <flux:input wire:model="smtpHost" :label="__('Servidor SMTP')" placeholder="smtp.ejemplo.com" />
                <flux:input wire:model="smtpPort" :label="__('Puerto SMTP')" placeholder="587" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeFormModal">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
