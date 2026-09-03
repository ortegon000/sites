<?php

use App\Models\Charge;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public Project $project;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    #[Computed]
    public function charges()
    {
        return Charge::query()
            ->whereHas('service', fn ($query) => $query->where('project_id', $this->project->id))
            ->with(['service', 'payments'])
            ->orderBy('due_date')
            ->get();
    }

    public function render()
    {
        return $this->view()->title($this->project->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-breadcrumbs :home="route('portal.services.index')" :items="[['label' => $project->name]]" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ $project->name }}</flux:heading>

        <flux:badge size="lg">{{ $project->status->label() }}</flux:badge>
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Datos generales') }}</flux:heading>

        <div class="flex flex-col gap-1 text-sm">
            <span class="text-zinc-400">{{ __('Descripción') }}</span>
            <span>{{ $project->description ?? '—' }}</span>
        </div>
        <div class="flex flex-col gap-1 text-sm">
            <span class="text-zinc-400">{{ __('Fecha de inicio') }}</span>
            <span>{{ $project->started_at?->format('d/m/Y') ?? '—' }}</span>
        </div>
    </flux:card>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Servicios') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                <flux:table.column>{{ __('Facturación') }}</flux:table.column>
                <flux:table.column>{{ __('Monto') }}</flux:table.column>
                <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($project->services as $service)
                    <flux:table.row wire:key="portal-service-{{ $service->id }}">
                        <flux:table.cell>{{ $service->name }}</flux:table.cell>
                        <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $service->amount }} {{ $service->currency }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">
                            {{ __('Sin servicios todavía.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Cobros') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Concepto') }}</flux:table.column>
                <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                <flux:table.column>{{ __('Monto') }}</flux:table.column>
                <flux:table.column>{{ __('Restante') }}</flux:table.column>
                <flux:table.column>{{ __('Estatus') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->charges as $charge)
                    <flux:table.row wire:key="portal-charge-{{ $charge->id }}">
                        <flux:table.cell>{{ $charge->conceptLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($charge->remainingAmount(), 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$charge->status->color()">
                                {{ $charge->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('Sin cobros todavía.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
