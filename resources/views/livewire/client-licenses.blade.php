<flux:card class="flex flex-col gap-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">{{ __('Licencias y suscripciones') }}</flux:heading>
                @if ($this->licenses->isNotEmpty())
                    <flux:badge size="sm" color="zinc">{{ $this->licenses->count() }}</flux:badge>
                @endif
            </div>
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Brevo, Elementor, WhatsApp Business… la anual avisa al cliente, igual que un dominio; la mensual solo nos recuerda a nosotros.') }}
            </flux:text>
        </div>

        @can('update', $client)
            <flux:button size="sm" icon="plus" wire:click="openLicenseModal">{{ __('Agregar') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->licenses as $license)
            @php
                $daysLeft = $license->renewal_date ? (int) now()->startOfDay()->diffInDays($license->renewal_date, false) : null;
            @endphp
            <div wire:key="license-{{ $license->id }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:icon name="key" variant="outline" class="size-5 shrink-0 text-zinc-400" />
                            <span class="text-base font-semibold">{{ $license->name }}</span>
                            <flux:badge size="sm" :color="$license->status->color()">{{ $license->status->label() }}</flux:badge>
                            @if ($daysLeft !== null)
                                <flux:badge size="sm" :color="$daysLeft < 0 ? 'red' : ($daysLeft <= 30 ? 'amber' : 'zinc')" icon="clock">
                                    @if ($daysLeft < 0)
                                        {{ __('Venció hace :days días', ['days' => abs($daysLeft)]) }}
                                    @elseif ($daysLeft === 0)
                                        {{ __('Renueva hoy') }}
                                    @else
                                        {{ __('Renueva en :days días', ['days' => $daysLeft]) }}
                                    @endif
                                </flux:badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($license->vendor)
                                <span>{{ $license->vendor }}</span>
                            @endif
                            @if ($license->domain)
                                <span class="flex items-center gap-1"><flux:icon name="globe-alt" variant="micro" />{{ $license->domain->name }}</span>
                            @endif
                            @if ($license->renewal_date)
                                <span>{{ $license->renewal_date->format('d/m/Y') }} · {{ $license->billing_frequency->label() }}</span>
                            @endif
                            @if ($license->cost)
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ number_format((float) $license->cost, 2) }} {{ $license->currency }}</span>
                            @endif
                            @if ($license->auto_renew)
                                <span class="flex items-center gap-1"><flux:icon name="arrow-path" variant="micro" />{{ __('Renovación automática') }}</span>
                            @endif
                        </div>
                    </div>

                    @can('update', $client)
                        <div class="flex shrink-0 items-center gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" :tooltip="__('Editar licencia')" wire:click="openLicenseModal({{ $license->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" :tooltip="__('Eliminar licencia')"
                                wire:click="deleteLicense({{ $license->id }})"
                                wire:confirm="{{ __('¿Eliminar esta licencia?') }}" />
                        </div>
                    @endcan
                </div>

                @if ($license->username || ($this->canSeeCredentials && $license->password))
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
                        @if ($license->username)
                            <span class="flex items-center gap-1.5"><flux:icon name="user" variant="micro" />{{ $license->username }}</span>
                        @endif

                        @if ($this->canSeeCredentials && $license->password)
                            <span class="flex items-center gap-1.5">
                                <flux:icon name="lock-closed" variant="micro" />
                                @if (array_key_exists($license->id, $revealedPasswords))
                                    <span class="font-mono text-zinc-800 dark:text-zinc-100">{{ $revealedPasswords[$license->id] }}</span>
                                    <flux:button size="xs" variant="ghost" icon="eye-slash" wire:click="hidePassword({{ $license->id }})" />
                                @else
                                    <span>••••••••</span>
                                    <flux:button size="xs" variant="ghost" icon="eye" wire:click="revealPassword({{ $license->id }})" />
                                @endif
                            </span>
                        @endif
                    </div>
                @endif

                @if ($license->notes)
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $license->notes }}</flux:text>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-zinc-300 py-8 text-center dark:border-white/15">
                <flux:icon name="key" variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-400">{{ __('Sin licencias registradas.') }}</flux:text>
            </div>
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

                    <flux:select wire:model="billingFrequency" :label="__('Facturación')"
                        :description="__('Mensual solo avisa por dentro; anual le avisa al cliente.')">
                        @foreach ($this->billingFrequencyOptions as $option)
                            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="status" :label="__('Estatus')" class="col-span-2">
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
                    <flux:input wire:model="password" type="password" :label="__('Contraseña')" viewable
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
