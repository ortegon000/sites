@props([
    'sidebar' => false,
])

{{--
    El logo con su nombre: azul sobre tema claro y blanco sobre tema oscuro,
    cada uno un PNG propio en public/images.
--}}
<a {{ $attributes->class(['flex items-center', 'px-2 py-1' => $sidebar]) }}>
    <img src="{{ asset('images/logo-blue.png') }}" alt="{{ config('app.name') }}" class="h-7 w-auto dark:hidden">
    <img src="{{ asset('images/logo-white.png') }}" alt="{{ config('app.name') }}" class="hidden h-7 w-auto dark:block">
</a>
