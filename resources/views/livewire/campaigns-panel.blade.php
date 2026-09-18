<flux:card class="flex flex-col gap-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <flux:heading size="lg">{{ __('Campañas de ads') }}</flux:heading>
            @if ($this->campaigns->isNotEmpty())
                <flux:badge size="sm" color="zinc">{{ $this->campaigns->count() }}</flux:badge>
            @endif
        </div>

        <flux:button size="sm" icon="plus" wire:click="openCampaignModal">{{ __('Agregar campaña') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->campaigns as $campaign)
            <div wire:key="campaign-{{ $campaign->id }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:icon name="megaphone" variant="outline" class="size-5 shrink-0 text-zinc-400" />
                            <span class="text-base font-semibold">{{ $campaign->name }}</span>
                            <flux:badge size="sm" :color="$campaign->status->color()">{{ $campaign->status->label() }}</flux:badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ $campaign->platform->label() }}</span>
                            @if ($campaign->ad_account_id)
                                <span>{{ $campaign->ad_account_id }}</span>
                            @endif
                            @if ($campaign->objective)
                                <span>{{ $campaign->objective }}</span>
                            @endif
                            @if ($campaign->starts_on)
                                <span class="flex items-center gap-1">
                                    <flux:icon name="calendar-days" variant="micro" />
                                    {{ $campaign->starts_on->format('d/m/Y') }}@if ($campaign->ends_on) — {{ $campaign->ends_on->format('d/m/Y') }}@endif
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" :tooltip="__('Editar campaña')" wire:click="openCampaignModal({{ $campaign->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" :tooltip="__('Eliminar campaña')" wire:click="deleteCampaign({{ $campaign->id }})" wire:confirm="{{ __('¿Eliminar esta campaña?') }}" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-white/5">
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-semibold tabular-nums">{{ number_format((float) $campaign->monthly_budget, 2) }} {{ $campaign->currency }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('al mes') }} · {{ $campaign->budget_billing->label() }}</span>
                    </div>

                    @if ($campaign->budget_billing->isBilledByUs())
                        @if ($campaign->services->isNotEmpty())
                            <span class="flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="check-circle" variant="micro" class="text-green-600 dark:text-green-500" />
                                {{ __('Se cobra como servicio') }}: {{ $campaign->services->first()->name }}
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-500">
                                <flux:icon name="exclamation-triangle" variant="micro" />
                                {{ __('Sin servicio de inversión: el presupuesto no está generando cobros.') }}
                            </span>
                        @endif
                    @else
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Solo de referencia: no genera cobros nuestros.') }}
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-zinc-300 py-8 text-center dark:border-white/15">
                <flux:icon name="megaphone" variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-400">{{ __('Sin campañas todavía.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal name="campaign-form" class="md:w-[32rem]">
        <form wire:submit="saveCampaign" class="flex flex-col gap-6">
            <flux:heading size="lg">
                {{ $editingCampaignId ? __('Editar campaña') : __('Nueva campaña') }}
            </flux:heading>

            <flux:input wire:model="campaignName" :label="__('Nombre')" autofocus />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="platform" :label="__('Plataforma')">
                    @foreach ($this->platformOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="adAccountId" :label="__('ID de cuenta publicitaria')" />

                <flux:input wire:model="objective" :label="__('Objetivo')" />

                <flux:select wire:model="campaignStatus" :label="__('Estatus')">
                    @foreach ($this->campaignStatusOptions as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="campaignStartsOn" type="date" :label="__('Inicio')" />

                <flux:input wire:model="campaignEndsOn" type="date" :label="__('Fin (opcional)')" />
            </div>

            <flux:separator />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="monthlyBudget" type="number" step="0.01" :label="__('Presupuesto mensual')" />
                <flux:input wire:model="currency" :label="__('Moneda')" maxlength="3" />
            </div>

            <flux:select wire:model.live="budgetBilling" :label="__('¿Quién paga el presupuesto?')">
                @foreach ($this->budgetBillingOptions as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($budgetBilling === \App\Enums\AdBudgetBilling::PassThrough->value && $editingCampaignId === null)
                <flux:checkbox wire:model="createBudgetService" :label="__('Crear servicio mensual de inversión publicitaria')"
                    :description="__('Genera los cobros del presupuesto, separados del fee de gestión.')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeCampaignModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
