---
paths:
  - 'resources/views/contracts/**'
---

# Contracts

## La plantilla del contrato es texto plano: cuidado con los saltos de línea de Blade
`resources/views/contracts/default.blade.php` genera texto plano, no HTML, así que los saltos de línea importan y Blade se los come en dos casos: una línea que **termina** en una directiva (`...@endif` o `...@if (...)`) pierde su salto de línea, y una directiva sola en su línea consume la línea entera.

La regla práctica: termina siempre las líneas de contenido con `}}` o con texto —usa un ternario dentro de `{{ }}` en vez de un `@if` inline al final de la línea— y deja las directivas solas en su propia línea. Además, `@endif` pegado a una palabra (`naturales@endif`) no compila: Blade exige que el `@` no venga después de un carácter de palabra.

`DraftContract` normaliza los `\n{3,}` que dejan las ramas de la plantilla, así que no persigas huecos sueltos a mano.
