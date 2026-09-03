<flux:card class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-col">
            <flux:heading size="lg">{{ __('Tareas') }}</flux:heading>
            <flux:text class="text-xs text-zinc-400">{{ __('Lo que hay que hacer en este proyecto. No es lo que se le cobra: eso son los servicios.') }}</flux:text>
        </div>
    </div>

    @if ($this->canManageTasks)
        <form wire:submit="addTask" class="flex flex-wrap items-end gap-2">
            <flux:input wire:model="quickTitle" size="sm" :label="__('Tarea')"
                :placeholder="__('Maquetar la página de contacto')" class="min-w-56 flex-1" />

            <flux:input wire:model="quickDueDate" type="date" size="sm" :label="__('Para cuándo')" class="w-40" />

            <flux:select wire:model="quickAssignee" size="sm" :label="__('Responsable')" class="w-44">
                <flux:select.option value="">{{ __('Sin asignar') }}</flux:select.option>
                @foreach ($this->assignableUsers as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" size="sm" variant="primary" icon="plus">{{ __('Capturar') }}</flux:button>
        </form>

        <flux:error name="quickTitle" />
        <flux:error name="quickAssignee" />
    @endif

    <div class="flex flex-col gap-3">
        @forelse ($this->tasks as $task)
            <div wire:key="project-task-{{ $task->id }}" class="flex flex-col gap-2 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <flux:button size="xs" variant="ghost"
                            :icon="$task->isDone() ? 'check-circle' : 'minus-circle'"
                            :tooltip="$task->isDone() ? __('Marcar pendiente') : __('Marcar hecha')"
                            wire:click="toggleTask({{ $task->id }})" />

                        <div class="flex flex-col text-sm">
                            <span class="{{ $task->isDone() ? 'text-zinc-400 line-through' : '' }}">{{ $task->title }}</span>
                            <span class="text-xs {{ $task->isOverdue() ? 'text-red-500' : 'text-zinc-400' }}">
                                {{ $task->due_date?->format('d/m/Y') ?? __('Sin fecha') }}
                                @if ($task->assignee)
                                    · {{ $task->assignee->name }}
                                @endif
                                @if ($task->subtasks->isNotEmpty())
                                    · {{ __(':done/:total subtareas', [
                                        'done' => $task->subtasks->whereNotNull('completed_at')->count(),
                                        'total' => $task->subtasks->count(),
                                    ]) }}
                                @endif
                            </span>
                        </div>
                    </div>

                    @if ($this->canManageTasks)
                        <div class="flex shrink-0 gap-1">
                            <flux:button size="xs" variant="ghost" icon="plus"
                                :tooltip="__('Agregar subtarea')"
                                wire:click="startSubtask({{ $task->id }})" />
                            <flux:button size="xs" variant="ghost" icon="pencil"
                                :tooltip="__('Editar')"
                                wire:click="openTaskModal({{ $task->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash"
                                :tooltip="__('Eliminar')"
                                wire:click="deleteTask({{ $task->id }})"
                                wire:confirm="{{ __('¿Eliminar esta tarea? Se van también sus subtareas.') }}" />
                        </div>
                    @endif
                </div>

                @if ($task->subtasks->isNotEmpty())
                    <div class="ms-9 flex flex-col gap-1">
                        @foreach ($task->subtasks as $subtask)
                            <div wire:key="project-subtask-{{ $subtask->id }}" class="flex items-center justify-between gap-3 text-sm">
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" variant="ghost"
                                        :icon="$subtask->isDone() ? 'check-circle' : 'minus-circle'"
                                        :tooltip="$subtask->isDone() ? __('Marcar pendiente') : __('Marcar hecha')"
                                        wire:click="toggleTask({{ $subtask->id }})" />
                                    <span class="{{ $subtask->isDone() ? 'text-zinc-400 line-through' : '' }}">{{ $subtask->title }}</span>
                                </div>

                                @if ($this->canManageTasks)
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                        :tooltip="__('Eliminar')"
                                        wire:click="deleteTask({{ $subtask->id }})" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($addingSubtaskFor === $task->id)
                    <form wire:submit="addSubtask" class="ms-9 flex flex-wrap items-end gap-2">
                        <flux:input wire:model="subtaskTitle" size="sm" autofocus
                            :placeholder="__('Subtarea')" class="min-w-56 flex-1" />
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Agregar') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="cancelSubtask">{{ __('Cancelar') }}</flux:button>

                        <flux:error name="subtaskTitle" />
                    </form>
                @endif
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('Sin tareas. Captura arriba lo que falta por hacer.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="project-task-form" class="md:w-96">
        <form wire:submit="saveTask" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Editar tarea') }}</flux:heading>

            <flux:input wire:model="taskTitle" :label="__('Tarea')" required autofocus />

            <flux:input wire:model="taskDueDate" type="date" :label="__('Para cuándo')" />

            <flux:select wire:model="taskAssignee" :label="__('Responsable')">
                <flux:select.option value="">{{ __('Sin asignar') }}</flux:select.option>
                @foreach ($this->assignableUsers as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeTaskModal">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Guardar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
