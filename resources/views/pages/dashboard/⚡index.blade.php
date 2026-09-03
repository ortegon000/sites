<?php

use App\Enums\ChargeStatus;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ProjectStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Project;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        if (auth()->user()->isClient()) {
            $this->redirect(route('portal.projects.index'), navigate: true);
        }
    }

    #[Computed]
    public function pendingCharges(): object
    {
        return Charge::query()
            ->whereIn('status', [ChargeStatus::Pendiente, ChargeStatus::Parcial])
            ->selectRemainingTotals()
            ->first();
    }

    #[Computed]
    public function overdueCharges(): object
    {
        return Charge::query()
            ->where('status', ChargeStatus::Vencido)
            ->selectRemainingTotals()
            ->first();
    }

    #[Computed]
    public function activeProjectsCount(): int
    {
        return Project::query()
            ->where('status', ProjectStatus::Activo)
            ->when(auth()->user()->isCollaborator(), fn ($query) => $query->whereHas('users', fn ($q) => $q->whereKey(auth()->id())))
            ->count();
    }

    #[Computed]
    public function openProspectsCount(): int
    {
        return Client::query()
            ->where('type', ClientType::Prospect)
            ->whereIn('status', [ClientStatus::Nuevo, ClientStatus::Contactado, ClientStatus::PropuestaEnviada])
            ->count();
    }

    /**
     * @return Collection<int, Charge>
     */
    #[Computed]
    public function upcomingCharges()
    {
        return Charge::query()
            ->whereIn('status', ChargeStatus::open())
            ->whereBetween('due_date', [today(), today()->addDays(7)])
            ->with(['service.project.client', 'payments'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @return Collection<int, ClientNote>
     */
    #[Computed]
    public function recentActivity()
    {
        return ClientNote::query()
            ->with(['client', 'author'])
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function myAssignedProjects()
    {
        return Project::query()
            ->whereHas('users', fn ($q) => $q->whereKey(auth()->id()))
            ->where('status', ProjectStatus::Activo)
            ->with('client')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return $this->view()->title(__('Dashboard'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-zinc-400">{{ __('Cobros pendientes') }}</flux:text>
                <flux:heading size="lg">{{ $this->pendingCharges->count }}</flux:heading>
                <flux:text class="text-sm text-zinc-400">{{ __('Por cobrar: :amount', ['amount' => number_format((float) $this->pendingCharges->total, 2)]) }}</flux:text>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-zinc-400">{{ __('Cobros vencidos') }}</flux:text>
                <flux:heading size="lg" class="text-red-500">{{ $this->overdueCharges->count }}</flux:heading>
                <flux:text class="text-sm text-zinc-400">{{ __('Por cobrar: :amount', ['amount' => number_format((float) $this->overdueCharges->total, 2)]) }}</flux:text>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-zinc-400">{{ __('Proyectos activos') }}</flux:text>
                <flux:heading size="lg">{{ $this->activeProjectsCount }}</flux:heading>
            </flux:card>

            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-zinc-400">{{ __('Prospectos abiertos') }}</flux:text>
                <flux:heading size="lg">{{ $this->openProspectsCount }}</flux:heading>
            </flux:card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Próximos cobros (7 días)') }}</flux:heading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                        <flux:table.column>{{ __('Concepto') }}</flux:table.column>
                        <flux:table.column>{{ __('Vencimiento') }}</flux:table.column>
                        <flux:table.column>{{ __('Monto') }}</flux:table.column>
                        <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->upcomingCharges as $charge)
                            <flux:table.row wire:key="upcoming-charge-{{ $charge->id }}">
                                <flux:table.cell>
                                    <flux:link :href="route('projects.show', $charge->service->project)" wire:navigate>
                                        {{ $charge->service->project->client->name }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ $charge->conceptLabel() }}</flux:table.cell>
                                <flux:table.cell>{{ $charge->due_date->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-col">
                                        <span>{{ number_format((float) $charge->amount, 2) }} {{ $charge->currency }}</span>
                                        @if ($charge->payments->isNotEmpty())
                                            <span class="text-xs text-zinc-400">
                                                {{ __('restan :amount', ['amount' => number_format($charge->remainingAmount(), 2)]) }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$charge->status->color()">
                                        {{ $charge->status->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-zinc-400">
                                    {{ __('Sin cobros próximos a vencer.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Actividad reciente') }}</flux:heading>

                <div class="flex flex-col gap-3">
                    @forelse ($this->recentActivity as $note)
                        <div wire:key="activity-{{ $note->id }}" class="flex flex-col gap-1 border-b border-zinc-100 pb-3 text-sm last:border-0 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <flux:link :href="route('clients.show', $note->client)" wire:navigate>
                                    {{ $note->client->name }}
                                </flux:link>
                                <span class="text-xs text-zinc-400">{{ $note->created_at->diffForHumans() }}</span>
                            </div>
                            <span class="text-zinc-400">{{ $note->author?->name ?? __('Sistema') }} · {{ $note->type->label() }}</span>
                            <span>{{ \Illuminate\Support\Str::limit($note->body, 120) }}</span>
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin actividad reciente.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-zinc-400">{{ __('Mis proyectos activos') }}</flux:text>
                <flux:heading size="lg">{{ $this->activeProjectsCount }}</flux:heading>
            </flux:card>
        </div>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Mis proyectos asignados') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Cliente') }}</flux:table.column>
                    <flux:table.column>{{ __('Proyecto') }}</flux:table.column>
                    <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->myAssignedProjects as $project)
                        <flux:table.row wire:key="my-project-{{ $project->id }}">
                            <flux:table.cell>{{ $project->client->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('projects.show', $project)" wire:navigate>
                                    {{ $project->name }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ $project->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-400">
                                {{ __('Sin proyectos asignados.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
