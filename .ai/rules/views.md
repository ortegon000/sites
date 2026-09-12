---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## class="..." en flux:input no llega al elemento flex/grid — usa field:class
Un `class="flex-1"` (o cualquier utilidad de layout: ancho, grow, grid-column) puesto directo en `<flux:input>` no llega al elemento que en verdad participa en el flex/grid del contenedor. Flux solo reenvía `class` sin prefijo al div interno `data-flux-input` (el que envuelve el `<input>`), mientras que el elemento raíz real del campo es `<ui-field>` (`display: grid`), que viene de `flux:with-field`/`flux:field` y solo recibe atributos con el prefijo `field:`.

Por eso un input dentro de un `<div class="flex ...">` que debía repartirse el ancho con un botón de al lado se quedaba angosto aunque tuviera `class="flex-1"`: ese `flex-1` terminaba en el div interno, no en `<ui-field>`. La solución es `field:class="flex-1"` (o el layout que corresponda) en vez de `class="..."` cuando lo que se ajusta es cómo el campo completo (label + input + descripción) se comporta como hijo de un flex/grid padre.

## Un wire:click sin parámetros en un @foreach activa el "cargando" de toda la fila... de todas las filas
Flux deriva `wire:target` del propio `wire:click` cuando no se especifica explícitamente (ver `flux/button/index.blade.php`). Si un botón dentro de un `@foreach`/`@forelse` llama a un método sin parámetros que lo distinga por fila (p. ej. `wire:click="markCopied"` en vez de `wire:click="markCopied({{ $item->id }})"`), todas las filas comparten el mismo `wire:target`, y Livewire marca como "cargando" (`data-flux-loading`) el botón correspondiente en TODAS las filas cuando se pulsa en cualquiera de ellas, no solo en la que se clickeó.

La regla práctica: todo `wire:click` dentro de un loop debe llevar el id de la fila como argumento, aunque el método no lo use para nada más que para diferenciar su `wire:target` (ver `markCopied(int $quoteId)` en `App\Concerns\ManagesQuoteActions`, cuyo único propósito del parámetro es ese).
