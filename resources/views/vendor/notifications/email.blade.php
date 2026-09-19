<x-mail::message>
{{-- Saludo --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# ¡Ups!
@else
# Hola
@endif
@endif

{{-- Líneas de introducción --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Datos clave --}}
@isset($details)
<x-mail::details :rows="$details" />
@endisset

@isset($footnote)
{{ $footnote }}

@endisset

{{-- Botón de acción --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Líneas de cierre --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Despedida --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Saludos,<br>
{{ config('app.name') }}
@endif

{{-- Enlace alterno --}}
@isset($actionText)
<x-slot:subcopy>
Si el botón «{{ $actionText }}» no funciona, copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
