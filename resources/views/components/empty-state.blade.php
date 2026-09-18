@props(['icon' => null, 'bordered' => false])

<div {{ $attributes->class([
    'flex flex-col items-center gap-2 py-8 text-center',
    'rounded-xl border border-dashed border-zinc-300 dark:border-white/15' => $bordered,
]) }}>
    @if ($icon)
        <flux:icon :name="$icon" variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600" />
    @endif

    <flux:text class="max-w-md text-zinc-500 dark:text-zinc-400">{{ $slot }}</flux:text>
</div>
