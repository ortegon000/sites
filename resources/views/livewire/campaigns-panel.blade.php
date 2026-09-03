<flux:card class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="lg">{{ __('Campañas de ads') }}</flux:heading>

        <flux:button size="sm" icon="plus" wire:click="openCampaignModal">{{ __('Agregar campaña') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->campaigns as $campaign)
            <div wire:key="campaign-{{ $campaign->id }}" class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ $campaign->name }}</span>
                        <span class="text-xs text-zinc-400">
                            {{ $campaign->platform->label() }}
                            @if ($campaign->ad_account_id)
                                · {{ $campaign->ad_account_id }}
                            @endif
                            @if ($campaign->objective)
                                · {{ $campaign->objective }}
                            @endif
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:badge size="sm">{{ $campaign->status->label() }}</flux:badge>
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openCampaignModal({{ $campaign->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteCampaign({{ $campaign->id }})" wire:confirm="{{ __('¿Eliminar esta campaña?') }}" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span>
                        <span class="text-zinc-400">{{ __('Presupuesto mensual') }}:</span>
                        {{ number_format((float) $campaign->monthly_budget, 2) }} {{ $campaign->currency }}
                    </span>
                    <span class="text-xs text-zinc-400">{{ $campaign->budget_billing->label() }}</span>
                </div>

                @if ($campaign->budget_billing->isBilledByUs())
                    @if ($campaign->services->isNotEmpty())
                        <flux:text class="text-xs text-zinc-400">
                            {{ __('Se cobra como servicio') }}: {{ $campaign->services->first()->name }}
                        </flux:text>
                    @else
                        <flux:text class="text-xs text-amber-600 dark:text-amber-500">
                            {{ __('Sin servicio de inversión: el presupuesto no está generando cobros.') }}
                        </flux:text>
                    @endif
                @else
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Solo de referencia: no genera cobros nuestros.') }}
                    </flux:text>
                @endif

                <span class="text-xs text-zinc-400">
                    {{ $campaign->starts_on?->format('d/m/Y') }}
                    @if ($campaign->ends_on)
                        — {{ $campaign->ends_on->format('d/m/Y') }}
                    @endif
                </span>
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('Sin campañas todavía.') }}</flux:text>
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
