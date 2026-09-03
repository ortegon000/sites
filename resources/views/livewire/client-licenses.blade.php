<flux:card class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg">{{ __('Licencias y suscripciones') }}</flux:heading>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Brevo, Elementor, WhatsApp Business… avisan antes de caducar, igual que los dominios.') }}
            </flux:text>
        </div>

        @can('update', $client)
            <flux:button size="sm" icon="plus" wire:click="openLicenseModal">{{ __('Agregar') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->licenses as $license)
            <div wire:key="license-{{ $license->id }}" class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-col">
                        <span class="font-medium">{{ $license->name }}</span>
                        <span class="text-xs text-zinc-400">
                            {{ $license->vendor ?? '—' }}
                            @if ($license->domain)
                                · {{ $license->domain->name }}
                            @endif
                            @if ($license->renewal_date)
                                · {{ __('Renueva') }} {{ $license->renewal_date->format('d/m/Y') }}
                            @endif
                        </span>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <flux:badge size="sm">{{ $license->status->label() }}</flux:badge>
                        @can('update', $client)
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openLicenseModal({{ $license->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash"
                                wire:click="deleteLicense({{ $license->id }})"
                                wire:confirm="{{ __('¿Eliminar esta licencia?') }}" />
                        @endcan
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    @if ($license->cost)
                        <span>{{ number_format((float) $license->cost, 2) }} {{ $license->currency }}</span>
                    @endif
                    @if ($license->auto_renew)
                        <span class="text-xs text-zinc-400">{{ __('Renovación automática') }}</span>
                    @endif
                    @if ($license->username)
                        <span class="text-xs text-zinc-400">{{ $license->username }}</span>
                    @endif

                    @if ($this->canSeeCredentials && $license->password)
                        <span class="flex items-center gap-2 text-xs">
                            @if (array_key_exists($license->id, $revealedPasswords))
                                <span class="font-mono">{{ $revealedPasswords[$license->id] }}</span>
                                <flux:button size="xs" variant="ghost" icon="eye-slash" wire:click="hidePassword({{ $license->id }})" />
                            @else
                                <span class="text-zinc-400">••••••••</span>
                                <flux:button size="xs" variant="ghost" icon="eye" wire:click="revealPassword({{ $license->id }})" />
                            @endif
                        </span>
                    @endif
                </div>

                @if ($license->notes)
                    <flux:text class="text-xs text-zinc-400">{{ $license->notes }}</flux:text>
                @endif
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('Sin licencias registradas.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="license-form" class="md:w-96">
        <form wire:submit="saveLicense" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingLicenseId ? __('Editar licencia') : __('Nueva licencia') }}
            </flux:heading>

            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" :label="__('Nombre')" placeholder="Brevo, Elementor Pro…" autofocus />
                <flux:input wire:model="vendor" :label="__('Proveedor')" />

                <flux:select wire:model="domainId" :label="__('Dominio')"
                    :description="__('Opcional. Solo si la licencia es de un sitio en particular.')">
                    <flux:select.option value="">{{ __('Del cliente, sin dominio específico') }}</flux:select.option>
                    @foreach ($this->assignableDomains as $domain)
                        <flux:select.option value="{{ $domain->id }}">{{ $domain->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="cost" type="number" step="0.01" :label="__('Costo')" />
                    <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />
                    <flux:input wire:model="renewalDate" type="date" :label="__('Renueva el')" />

                    <flux:select wire:model="status" :label="__('Estatus')">
                        @foreach ($this->statusOptions as $option)
                            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:checkbox wire:model="autoRenew" :label="__('Renovación automática')" />
            </div>

            @if ($this->canSeeCredentials)
                <div class="flex flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm">{{ __('Acceso') }}</flux:heading>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Se guarda cifrado y no se muestra en el portal del cliente.') }}
                        </flux:text>
                    </div>

                    <flux:input wire:model="url" :label="__('URL')" />
                    <flux:input wire:model="username" :label="__('Usuario')" />
                    <flux:input wire:model="password" :label="__('Contraseña')" viewable
                        :description="$editingLicenseId ? __('Déjalo vacío para conservar la guardada.') : null" />
                </div>
            @endif

            <flux:textarea wire:model="notes" :label="__('Notas')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeLicenseModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
