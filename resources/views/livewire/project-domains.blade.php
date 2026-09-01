<flux:card class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="lg">{{ __('Dominios y correo') }}</flux:heading>

        <flux:button size="sm" icon="plus" wire:click="openDomainModal">{{ __('Agregar dominio') }}</flux:button>
    </div>

    @if (! $project->includes_email)
        <flux:text class="text-xs text-zinc-400">
            {{ __('Este proyecto no incluye correo, así que sus dominios solo se registran para seguimiento. Activa "Incluye correo" en el proyecto para poder administrar buzones.') }}
        </flux:text>
    @endif

    <div class="flex flex-col gap-4">
        @forelse ($this->domains as $domain)
            <div wire:key="domain-{{ $domain->id }}" class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ $domain->name }}</span>
                        <span class="text-xs text-zinc-400">
                            {{ $domain->management->label() }}
                            @if ($domain->registrar)
                                · {{ $domain->registrar }}
                            @endif
                            @if ($domain->expires_at)
                                · {{ __('Expira') }} {{ $domain->expires_at->format('d/m/Y') }}
                            @endif
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:badge size="sm">{{ $domain->status->label() }}</flux:badge>
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openDomainModal({{ $domain->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteDomain({{ $domain->id }})" wire:confirm="{{ __('¿Eliminar este dominio y sus cuentas de correo?') }}" />
                    </div>
                </div>

                @if ($domain->managesEmail())
                    <flux:separator />

                    <div class="flex items-center justify-between gap-2">
                        <flux:text class="text-xs text-zinc-400">{{ __('Cuentas de correo') }}</flux:text>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" icon="arrow-down-tray" variant="ghost" wire:click="openImportModal({{ $domain->id }})">
                                {{ __('Importar') }}
                            </flux:button>
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openEmailModal({{ $domain->id }})">
                                {{ __('Agregar') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($domain->emailAccounts as $emailAccount)
                            <div wire:key="email-account-{{ $emailAccount->id }}" class="flex items-center justify-between gap-2 text-sm">
                                <div class="flex flex-col">
                                    <span>{{ $emailAccount->email_address }}</span>
                                    <span class="text-xs text-zinc-400">
                                        {{ $emailAccount->provider->name }} · {{ $emailAccount->origin->label() }}
                                    </span>
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
                @elseif ($domain->email_notes)
                    <flux:text class="text-xs text-zinc-400">{{ __('Correo') }}: {{ $domain->email_notes }}</flux:text>
                @endif
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('Sin dominios todavía.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="domain-form" class="md:w-[32rem]">
        <form wire:submit="saveDomain" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingDomainId ? __('Editar dominio') : __('Agregar dominio') }}
            </flux:heading>

            <flux:input wire:model="domainName" :label="__('Dominio')" placeholder="acme.com" autofocus />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="management" :label="__('Administración')">
                    @foreach ($this->managementOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="domainStatus" :label="__('Estatus')">
                    @foreach ($this->domainStatusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="registrar" :label="__('Registrador')" />

                <flux:input wire:model="registeredAt" type="date" :label="__('Registrado el')" />

                <flux:input wire:model="expiresAt" type="date" :label="__('Expira el')" />
            </div>

            <flux:checkbox wire:model="autoRenew" :label="__('Renovación automática')" />

            <flux:separator />

            <flux:select wire:model.live="emailManagement" :label="__('Correo')" :disabled="! $project->includes_email">
                <flux:select.option value="not_managed">{{ __('No administramos el correo') }}</flux:select.option>
                <flux:select.option value="managed">{{ __('Administramos el correo') }}</flux:select.option>
            </flux:select>

            @if ($emailManagement !== 'managed')
                <flux:textarea wire:model="emailNotes" :label="__('Notas del correo (opcional)')" rows="2"
                    :placeholder="__('Ej. Google Workspace del cliente')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeDomainModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="email-account-form" class="md:w-96">
        <form wire:submit="provisionEmailAccount" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Nueva cuenta de correo') }}</flux:heading>

            <flux:select wire:model="emailProviderIdToAssign" :label="__('Proveedor')">
                <flux:select.option value="">{{ __('Selecciona un proveedor') }}</flux:select.option>
                @foreach ($this->activeEmailProviders as $provider)
                    <flux:select.option value="{{ $provider->id }}">{{ $provider->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="newEmailAddress" type="email" :label="__('Correo')" />

            <flux:input wire:model="newEmailPassword" type="password" :label="__('Contraseña')" viewable />

            <flux:error name="emailDomainId" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeEmailModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Crear') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="email-import" class="md:w-96">
        <form wire:submit="importEmailAccounts" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Importar buzones existentes') }}</flux:heading>
                <flux:text class="text-xs text-zinc-400">
                    {{ __('Consulta los buzones que ya existen en el proveedor y elige cuáles registrar aquí. Los que no marques quedan fuera del sistema.') }}
                </flux:text>
            </div>

            <div class="flex items-end gap-2">
                <flux:select wire:model="importProviderId" :label="__('Proveedor')" class="flex-1">
                    <flux:select.option value="">{{ __('Selecciona un proveedor') }}</flux:select.option>
                    @foreach ($this->activeEmailProviders as $provider)
                        <flux:select.option value="{{ $provider->id }}">{{ $provider->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button size="sm" wire:click="loadImportCandidates">{{ __('Consultar') }}</flux:button>
            </div>

            @if ($importCandidates !== [])
                <div class="flex flex-col gap-2">
                    @foreach ($importCandidates as $candidate)
                        <flux:checkbox wire:key="import-{{ $loop->index }}" wire:model="selectedImports"
                            value="{{ $candidate }}" :label="$candidate" />
                    @endforeach
                </div>
            @elseif ($importProviderId)
                <flux:text class="text-zinc-400">{{ __('El proveedor no reporta buzones nuevos para este dominio.') }}</flux:text>
            @endif

            <flux:error name="selectedImports" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeImportModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Vincular') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="email-password-form" class="md:w-80">
        <form wire:submit="changePassword" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Cambiar contraseña') }}</flux:heading>

            <flux:input wire:model="newPassword" type="password" :label="__('Nueva contraseña')" viewable autofocus />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closePasswordModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
