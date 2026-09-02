@props(['current' => 'companies'])

@php
    $tabs = [
        'companies' => ['label' => __('Empresas'), 'href' => route('clients.index')],
        'contacts' => ['label' => __('Contactos'), 'href' => route('contacts.index')],
    ];
@endphp

<div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
    @foreach ($tabs as $key => $tab)
        <a href="{{ $tab['href'] }}" wire:navigate
            @class([
                '-mb-px border-b-2 px-3 py-2 text-sm transition',
                'border-zinc-900 font-medium text-zinc-900 dark:border-white dark:text-white' => $current === $key,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' => $current !== $key,
            ])>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
