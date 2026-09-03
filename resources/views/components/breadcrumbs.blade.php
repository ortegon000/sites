@props([
    /** @var array<int, array{label: string, href?: string}> Cada paso del camino; el último es dónde estás parado y no lleva enlace. */
    'items' => [],
    /** A dónde va la casita. El portal no tiene dashboard, así que apunta a su propia entrada. */
    'home' => null,
])

{{--
    La ruta de migas de la app: dice dónde estás y por dónde volver, que hace
    falta desde que pantallas como Proyectos o Contratos dejaron de tener
    renglón en el menú y se llega a ellas desde la ficha del cliente.
--}}
<flux:breadcrumbs {{ $attributes }}>
    <flux:breadcrumbs.item :href="$home ?? route('dashboard')" icon="home" />

    @foreach ($items as $item)
        <flux:breadcrumbs.item :href="$item['href'] ?? null">{{ $item['label'] }}</flux:breadcrumbs.item>
    @endforeach
</flux:breadcrumbs>
