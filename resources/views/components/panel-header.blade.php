@props(['title', 'count' => null, 'description' => null])

<div {{ $attributes->class('flex flex-wrap items-start justify-between gap-x-4 gap-y-2') }}>
    <div class="flex min-w-0 flex-col gap-1">
        <div class="flex items-center gap-2">
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if ($count)
                <flux:badge size="sm" color="zinc">{{ $count }}</flux:badge>
            @endif
        </div>

        @if ($description)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</flux:text>
        @endif
    </div>

    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
