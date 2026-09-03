<div class="relative">
    <flux:dropdown position="bottom" align="end">
        <flux:button icon="bell" variant="ghost" size="sm" square />

        @if ($unreadCount > 0)
            <flux:badge size="sm" color="red" class="pointer-events-none absolute -end-1 -top-1">
                {{ $unreadCount }}
            </flux:badge>
        @endif

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <flux:heading size="sm">{{ __('Notificaciones') }}</flux:heading>

                @if ($unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllAsRead">
                        {{ __('Marcar todas como leídas') }}
                    </flux:button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($notifications as $notification)
                <flux:menu.item
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="{{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <div class="flex flex-col gap-0.5 whitespace-normal text-start">
                        @if ($notification->data['type'] === 'domain_expiring')
                            <span class="text-sm">
                                {{ __('Dominio por expirar') }}: {{ $notification->data['domain_name'] }}
                            </span>
                            <span class="text-xs text-zinc-400">
                                {{ $notification->data['client_name'] }} · {{ __('expira') }} {{ $notification->data['expires_at'] }}
                            </span>
                        @else
                            <span class="text-sm">
                                @if ($notification->data['type'] === 'charge_overdue')
                                    {{ __('Cobro vencido') }}: {{ $notification->data['service_name'] }}
                                @else
                                    {{ __('Cobro próximo a vencer') }}: {{ $notification->data['service_name'] }}
                                @endif
                            </span>
                            <span class="text-xs text-zinc-400">
                                {{ $notification->data['project_name'] ?? $notification->data['client_name'] ?? __('Línea suelta') }}
                                · {{ $notification->data['amount'] }} {{ $notification->data['currency'] }}
                            </span>
                        @endif
                        <span class="text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-3 py-4 text-center text-sm text-zinc-400">
                    {{ __('Sin notificaciones.') }}
                </div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
