<?php

use App\Enums\AgencyStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Project;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public ?int $userIdToAssign = null;

    public ?int $agencyIdToAssign = null;

    public ?string $agencyNotes = null;

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    #[Computed]
    public function assignableUsers()
    {
        return User::query()
            ->whereIn('role', [UserRole::Staff, UserRole::Collaborator])
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($this->project->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function assignableAgencies()
    {
        return Agency::query()
            ->where('status', AgencyStatus::Activa)
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($this->project->id))
            ->orderBy('name')
            ->get();
    }

    public function assignUser(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'userIdToAssign' => ['required', 'exists:users,id'],
        ]);

        $this->project->users()->syncWithoutDetaching([$this->userIdToAssign]);

        $this->userIdToAssign = null;

        Flux::toast(variant: 'success', text: __('Usuario asignado.'));
    }

    public function unassignUser(int $userId): void
    {
        Gate::authorize('update', $this->project);

        $this->project->users()->detach($userId);

        Flux::toast(variant: 'success', text: __('Usuario removido del proyecto.'));
    }

    public function assignAgency(): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'agencyIdToAssign' => ['required', 'exists:agencies,id'],
            'agencyNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->project->agencies()->syncWithoutDetaching([
            $validated['agencyIdToAssign'] => ['notes' => $validated['agencyNotes']],
        ]);

        $this->reset(['agencyIdToAssign', 'agencyNotes']);

        Flux::toast(variant: 'success', text: __('Agencia asociada.'));
    }

    public function unassignAgency(int $agencyId): void
    {
        Gate::authorize('update', $this->project);

        $this->project->agencies()->detach($agencyId);

        Flux::toast(variant: 'success', text: __('Agencia removida del proyecto.'));
    }

    public function render()
    {
        return $this->view()->title($this->project->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
            <flux:text class="text-zinc-400">
                <flux:link :href="route('clients.show', $project->client)" wire:navigate>{{ $project->client->name }}</flux:link>
            </flux:text>
        </div>

        <flux:badge size="lg">{{ $project->status->label() }}</flux:badge>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="flex flex-col gap-6 md:col-span-1">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Datos generales') }}</flux:heading>

                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-zinc-400">{{ __('Tipo') }}</span>
                    <span>
                        {{ $project->type->label() }}
                        @if ($project->includes_email)
                            <span class="text-xs text-zinc-400">· {{ __('incluye correo') }}</span>
                        @endif
                    </span>
                </div>
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
                <flux:heading size="lg">{{ __('Equipo asignado') }}</flux:heading>

                @can('update', $project)
                    <form wire:submit="assignUser" class="flex gap-2">
                        <flux:select wire:model="userIdToAssign" class="flex-1">
                            <flux:select.option value="">{{ __('Selecciona un usuario') }}</flux:select.option>
                            @foreach ($this->assignableUsers as $user)
                                <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                    </form>

                    <flux:separator />
                @endcan

                <div class="flex flex-col gap-2">
                    @forelse ($project->users as $user)
                        <div wire:key="assigned-user-{{ $user->id }}" class="flex items-center justify-between text-sm">
                            <span>{{ $user->name }}</span>
                            @can('update', $project)
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unassignUser({{ $user->id }})" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="text-zinc-400">{{ __('Sin usuarios asignados.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>

            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Agencias') }}</flux:heading>

                    @can('update', $project)
                        <form wire:submit="assignAgency" class="flex flex-col gap-2">
                            <flux:select wire:model="agencyIdToAssign">
                                <flux:select.option value="">{{ __('Selecciona una agencia') }}</flux:select.option>
                                @foreach ($this->assignableAgencies as $agency)
                                    <flux:select.option value="{{ $agency->id }}">{{ $agency->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:textarea wire:model="agencyNotes" :placeholder="__('Notas (opcional)')" rows="2" />

                            <div class="flex justify-end">
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                            </div>
                        </form>

                        <flux:separator />
                    @endcan

                    <div class="flex flex-col gap-3">
                        @forelse ($project->agencies as $agency)
                            <div wire:key="assigned-agency-{{ $agency->id }}" class="flex flex-col gap-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700">
                                <div class="flex items-center justify-between text-sm">
                                    <span>{{ $agency->name }}</span>
                                    @can('update', $project)
                                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unassignAgency({{ $agency->id }})" />
                                    @endcan
                                </div>
                                <flux:badge size="sm" :color="$agency->billing_target === \App\Enums\AgencyBillingTarget::Agency ? 'blue' : 'zinc'">
                                    {{ __('Se factura :target', ['target' => mb_strtolower($agency->billing_target->label())]) }}
                                </flux:badge>
                                @if ($agency->pivot->notes)
                                    <span class="text-xs text-zinc-400">{{ $agency->pivot->notes }}</span>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('Sin agencias asociadas.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @endif
        </div>

        <div class="flex flex-col gap-6 md:col-span-2">
            @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                <livewire:services-panel :client="$project->client" :project="$project" :key="'services-panel-'.$project->id" />

                <livewire:charges-panel :client="$project->client" :project="$project" :key="'charges-panel-'.$project->id" />
            @else
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Servicios') }}</flux:heading>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                            <flux:table.column>{{ __('Facturación') }}</flux:table.column>
                            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($project->services as $service)
                                <flux:table.row wire:key="collaborator-service-{{ $service->id }}">
                                    <flux:table.cell>{{ $service->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $service->billing_frequency->label() }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm">{{ $service->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="3" class="text-center text-zinc-400">
                                        {{ __('Sin servicios todavía.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif
        </div>
    </div>
</div>
