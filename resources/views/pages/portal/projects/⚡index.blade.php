<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    public function mount(): void
    {
        abort_if(auth()->user()->contact_id === null, 403);
    }

    /**
     * Los proyectos de todas las empresas de esta persona. Un dueño con varias
     * empresas entra una sola vez y las ve todas, en vez de necesitar un acceso
     * por empresa.
     */
    #[Computed]
    public function projects()
    {
        return Project::query()
            ->whereIn('client_id', auth()->user()->clients()->select('clients.id'))
            ->with('client')
            ->orderBy('client_id')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function showsCompany(): bool
    {
        return auth()->user()->clients()->count() > 1;
    }

    public function render()
    {
        return $this->view()->title(__('Mis proyectos'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <flux:heading size="xl">{{ __('Mis proyectos') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            @if ($this->showsCompany)
                <flux:table.column>{{ __('Empresa') }}</flux:table.column>
            @endif
            <flux:table.column>{{ __('Estatus') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->projects as $project)
                <flux:table.row wire:key="portal-project-{{ $project->id }}">
                    <flux:table.cell>
                        <flux:link :href="route('portal.projects.show', $project)" wire:navigate>{{ $project->name }}</flux:link>
                    </flux:table.cell>
                    @if ($this->showsCompany)
                        <flux:table.cell>{{ $project->client->name }}</flux:table.cell>
                    @endif
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $project->status->label() }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-400">
                        {{ __('Todavía no tienes proyectos.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
