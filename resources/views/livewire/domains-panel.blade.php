<flux:card class="flex flex-col gap-5">
    <x-panel-header :title="__('Dominios y correo')" :count="$this->domains->count()">
        <x-slot:actions>
            <flux:button size="sm" icon="plus" wire:click="openDomainModal">{{ __('Agregar dominio') }}</flux:button>
        </x-slot:actions>
    </x-panel-header>

    <div class="flex flex-col gap-4">
        @forelse ($this->domains as $domain)
            @php
                $daysLeft = $domain->expires_at ? (int) now()->startOfDay()->diffInDays($domain->expires_at, false) : null;
            @endphp
            <div wire:key="domain-{{ $domain->id }}" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:icon name="globe-alt" variant="outline" class="size-5 shrink-0 text-zinc-400" />
                            <span class="text-base font-semibold">{{ $domain->name }}</span>
                            <flux:badge size="sm" :color="$domain->status->color()">{{ $domain->status->label() }}</flux:badge>
                            @if ($daysLeft !== null)
                                <flux:badge size="sm" :color="$daysLeft < 0 ? 'red' : ($daysLeft <= 30 ? 'amber' : 'zinc')" icon="clock">
                                    @if ($daysLeft < 0)
                                        {{ __('Expiró hace :days días', ['days' => abs($daysLeft)]) }}
                                    @elseif ($daysLeft === 0)
                                        {{ __('Expira hoy') }}
                                    @else
                                        {{ __('Expira en :days días', ['days' => $daysLeft]) }}
                                    @endif
                                </flux:badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ $domain->management->label() }}</span>
                            @if ($domain->registrar)
                                <span>{{ $domain->registrar }}</span>
                            @endif
                            @if ($domain->expires_at)
                                <span>{{ __('Vence') }} {{ $domain->expires_at->format('d/m/Y') }}</span>
                            @endif
                            @if ($domain->registration_cost !== null)
                                <span>{{ __('Registro') }} {{ number_format((float) $domain->registration_cost, 2) }} {{ $domain->currency }}</span>
                            @endif
                            @if ($domain->auto_renew)
                                <span class="flex items-center gap-1"><flux:icon name="arrow-path" variant="micro" />{{ __('Renovación automática') }}</span>
                            @endif
                            @if ($domain->site_url)
                                <a href="{{ $domain->site_url }}" target="_blank" rel="noopener" class="flex items-center gap-1 hover:text-zinc-900 hover:underline dark:hover:text-white">
                                    <flux:icon name="arrow-top-right-on-square" variant="micro" />{{ preg_replace('#^https?://#', '', rtrim($domain->site_url, '/')) }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" :tooltip="__('Editar dominio')" wire:click="openDomainModal({{ $domain->id }})" :aria-label="__('Editar dominio')" />
                        <flux:button size="xs" variant="ghost" icon="trash" :tooltip="__('Eliminar dominio')" wire:click="deleteDomain({{ $domain->id }})" wire:confirm="{{ __('¿Eliminar este dominio y sus cuentas de correo?') }}" :aria-label="__('Eliminar dominio')" />
                    </div>
                </div>

                @if ($domain->managesEmail())
                    <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                <flux:icon name="envelope" variant="micro" />
                                {{ __('Cuentas de correo') }}
                                <span class="font-normal">{{ $domain->emailAccounts->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="xs" icon="arrow-down-tray" variant="ghost" wire:click="openImportModal({{ $domain->id }})">
                                    {{ __('Importar') }}
                                </flux:button>
                                <flux:button size="xs" icon="plus" variant="ghost" wire:click="openEmailModal({{ $domain->id }})">
                                    {{ __('Agregar') }}
                                </flux:button>
                            </div>
                        </div>

                        <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                            @forelse ($domain->emailAccounts as $emailAccount)
                                <div wire:key="email-account-{{ $emailAccount->id }}" class="flex items-center justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                                    <div class="flex min-w-0 flex-col">
                                        <span class="truncate font-medium">{{ $emailAccount->email_address }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $emailAccount->provider->name }} · {{ $emailAccount->origin->label() }}
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <flux:badge size="sm" :color="$emailAccount->status->color()">{{ $emailAccount->status->label() }}</flux:badge>
                                        <flux:button size="xs" variant="ghost" icon="key"
                                            :tooltip="$emailAccount->password === null ? __('Registrar contraseña existente') : __('Cambiar contraseña')"
                                            wire:click="openPasswordModal({{ $emailAccount->id }})"
                                            :aria-label="$emailAccount->password === null ? __('Registrar contraseña existente') : __('Cambiar contraseña')" />
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteEmailAccount({{ $emailAccount->id }})" wire:confirm="{{ __('¿Eliminar esta cuenta de correo?') }}" :aria-label="__('Eliminar')" />
                                    </div>
                                </div>
                            @empty
                                <flux:text class="text-sm text-zinc-400">{{ __('Sin cuentas de correo todavía.') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                @elseif ($domain->email_notes)
                    <div class="flex items-center gap-2 rounded-lg bg-zinc-50 p-3 text-xs text-zinc-500 dark:bg-white/5 dark:text-zinc-400">
                        <flux:icon name="envelope" variant="micro" class="shrink-0" />
                        <span>{{ __('Correo') }}: {{ $domain->email_notes }}</span>
                    </div>
                @endif

                @if ($this->canSeeCredentials)
                    <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                <flux:icon name="lock-closed" variant="micro" />
                                {{ __('Accesos') }}
                                <span class="font-normal">{{ $domain->credentials->count() }}</span>
                            </div>
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openCredentialModal({{ $domain->id }})">
                                {{ __('Agregar') }}
                            </flux:button>
                        </div>

                        <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                            @forelse ($domain->credentials as $credential)
                                <div wire:key="credential-{{ $credential->id }}" class="flex items-start justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <span class="font-medium">
                                            {{ $credential->kind->label() }}
                                            @if ($credential->label)
                                                <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ $credential->label }}</span>
                                            @endif
                                        </span>
                                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $credential->username ?? '—' }}
                                            @if ($credential->url)
                                                · {{ $credential->url }}
                                            @endif
                                        </span>
                                        @if ($credential->password)
                                            <span class="flex items-center gap-1 text-xs">
                                                @if (array_key_exists($credential->id, $revealedCredentials))
                                                    <span class="font-mono">{{ $revealedCredentials[$credential->id] }}</span>
                                                    <flux:button size="xs" variant="ghost" icon="eye-slash"
                                                        wire:click="hideCredential({{ $credential->id }})" />
                                                @else
                                                    <span class="text-zinc-400">••••••••</span>
                                                    <flux:button size="xs" variant="ghost" icon="eye"
                                                        wire:click="revealCredential({{ $credential->id }})"
                                                        :aria-label="__('Mostrar contraseña')" />
                                                @endif
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <flux:button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openCredentialModal({{ $domain->id }}, {{ $credential->id }})"
                                            :aria-label="__('Editar')" />
                                        <flux:button size="xs" variant="ghost" icon="trash"
                                            wire:click="deleteCredential({{ $credential->id }})"
                                            wire:confirm="{{ __('¿Eliminar este acceso?') }}"
                                            :aria-label="__('Eliminar')" />
                                    </div>
                                </div>
                            @empty
                                <flux:text class="text-sm text-zinc-400">{{ __('Sin accesos registrados.') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state icon="globe-alt" bordered>{{ __('Sin dominios todavía.') }}</x-empty-state>
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

                <flux:input wire:model="registrationCost" type="number" step="0.01" :label="__('Costo de registro')" />

                <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />
            </div>

            <flux:input wire:model="siteUrl" :label="__('URL del sitio')" placeholder="https://acme.com" />

            <flux:checkbox wire:model="autoRenew" :label="__('Renovación automática')" />

            <flux:separator />

            <flux:select wire:model.live="emailManagement" :label="__('Correo')">
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

            <flux:select wire:model.live="emailProviderIdToAssign" :label="__('Proveedor')">
                <flux:select.option value="">{{ __('Selecciona un proveedor') }}</flux:select.option>
                @foreach ($this->activeEmailProviders as $provider)
                    <flux:select.option value="{{ $provider->id }}">{{ $provider->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="newEmailAddress" type="email" :label="__('Correo')" />

            @php $selectedProvider = $this->activeEmailProviders->firstWhere('id', (int) $emailProviderIdToAssign); @endphp

            <flux:input wire:model="newEmailPassword" type="password" :label="__('Contraseña')" viewable
                :description="$selectedProvider?->storesPasswordLocally() ? __('Opcional: puedes dejarla vacía y capturarla después.') : null" />

            <flux:error name="emailDomainId" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeEmailModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Crear') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="credential-form" class="md:w-96">
        <form wire:submit="saveCredential" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">
                    {{ $editingCredentialId ? __('Editar acceso') : __('Nuevo acceso') }}
                </flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Se guarda cifrado y no se muestra en el portal del cliente: es infraestructura, no su buzón.') }}
                </flux:text>
            </div>

            <div class="flex flex-col gap-4">
                <flux:select wire:model="credentialKind" :label="__('Tipo')">
                    @foreach ($this->credentialKindOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="credentialLabel" :label="__('Etiqueta')" :placeholder="__('Nombre de la base, del sitio…')" />
                <flux:input wire:model="credentialUrl" :label="__('URL')" placeholder="https://cpanel.acme.com" />
                <flux:input wire:model="credentialUsername" :label="__('Usuario')" />
                <flux:input wire:model="credentialPassword" type="password" :label="__('Contraseña')" viewable
                    :description="$editingCredentialId ? __('Déjalo vacío para conservar la guardada.') : null" />
                <flux:textarea wire:model="credentialNotes" :label="__('Notas')" rows="2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeCredentialModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
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
            <flux:heading size="lg">
                {{ $passwordAccountIsUnset && ! $settingNewPassword ? __('Registrar contraseña') : __('Cambiar contraseña') }}
            </flux:heading>

            @if ($passwordAccountIsUnset)
                <flux:checkbox wire:model.live="settingNewPassword" :label="__('No tengo la contraseña actual: generar una nueva')" />

                <flux:text class="text-xs text-zinc-400">
                    @if ($settingNewPassword)
                        {{ __('Se creará/cambiará esta contraseña directamente en el proveedor.') }}
                    @else
                        {{ __('Este buzón ya existe en el proveedor; esto solo guarda su contraseña aquí, no la cambia.') }}
                    @endif
                </flux:text>
            @endif

            <flux:input wire:model="newPassword" type="password" :label="__('Contraseña')" viewable autofocus />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closePasswordModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
