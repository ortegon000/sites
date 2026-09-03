<flux:card class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="lg">{{ __('Dominios y correo') }}</flux:heading>

        <flux:button size="sm" icon="plus" wire:click="openDomainModal">{{ __('Agregar dominio') }}</flux:button>
    </div>

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
                            @if ($domain->hosting_plan)
                                · {{ __('Plan') }} {{ $domain->hosting_plan }}
                            @endif
                            @if ($domain->expires_at)
                                · {{ __('Expira') }} {{ $domain->expires_at->format('d/m/Y') }}
                            @endif
                        </span>
                        @if (! $project && $domain->project)
                            <span class="text-xs text-zinc-400">{{ $domain->project->name }}</span>
                        @endif
                        @if ($domain->site_url)
                            <a href="{{ $domain->site_url }}" target="_blank" rel="noopener" class="text-xs text-zinc-400 hover:underline">
                                {{ $domain->site_url }}
                            </a>
                        @endif
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

                @if ($this->canSeeCredentials)
                    <flux:separator />

                    <div class="flex items-center justify-between gap-2">
                        <flux:text class="text-xs text-zinc-400">{{ __('Accesos') }}</flux:text>
                        <flux:button size="xs" icon="plus" variant="ghost" wire:click="openCredentialModal({{ $domain->id }})">
                            {{ __('Agregar') }}
                        </flux:button>
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($domain->credentials as $credential)
                            <div wire:key="credential-{{ $credential->id }}" class="flex items-start justify-between gap-2 text-sm">
                                <div class="flex min-w-0 flex-col">
                                    <span>
                                        {{ $credential->kind->label() }}
                                        @if ($credential->label)
                                            <span class="text-zinc-400">· {{ $credential->label }}</span>
                                        @endif
                                    </span>
                                    <span class="truncate text-xs text-zinc-400">
                                        {{ $credential->username ?? '—' }}
                                        @if ($credential->url)
                                            · {{ $credential->url }}
                                        @endif
                                    </span>
                                    @if ($credential->password)
                                        <span class="flex items-center gap-2 text-xs">
                                            @if (array_key_exists($credential->id, $revealedCredentials))
                                                <span class="font-mono">{{ $revealedCredentials[$credential->id] }}</span>
                                                <flux:button size="xs" variant="ghost" icon="eye-slash"
                                                    wire:click="hideCredential({{ $credential->id }})" />
                                            @else
                                                <span class="text-zinc-400">••••••••</span>
                                                <flux:button size="xs" variant="ghost" icon="eye"
                                                    wire:click="revealCredential({{ $credential->id }})" />
                                            @endif
                                        </span>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <flux:button size="xs" variant="ghost" icon="pencil-square"
                                        wire:click="openCredentialModal({{ $domain->id }}, {{ $credential->id }})" />
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                        wire:click="deleteCredential({{ $credential->id }})"
                                        wire:confirm="{{ __('¿Eliminar este acceso?') }}" />
                                </div>
                            </div>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Sin accesos registrados.') }}</flux:text>
                        @endforelse
                    </div>
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

            <flux:select wire:model.live="domainProjectId" :label="__('Proyecto')"
                :description="__('Opcional. Un dominio de puro hosting no necesita proyecto; el correo sí requiere uno que lo incluya.')">
                <flux:select.option value="">{{ __('Sin proyecto') }}</flux:select.option>
                @foreach ($this->assignableProjects as $assignableProject)
                    <flux:select.option value="{{ $assignableProject->id }}">{{ $assignableProject->name }}</flux:select.option>
                @endforeach
            </flux:select>

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

                <flux:input wire:model="hostingPlan" :label="__('Plan de hosting')" placeholder="full, basic, compartido…" />

                <flux:input wire:model="hostedSince" type="date" :label="__('Alojado desde')" />
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
                <flux:input wire:model="credentialPassword" :label="__('Contraseña')" viewable />
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
            <flux:heading size="lg">{{ __('Cambiar contraseña') }}</flux:heading>

            <flux:input wire:model="newPassword" type="password" :label="__('Nueva contraseña')" viewable autofocus />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closePasswordModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
