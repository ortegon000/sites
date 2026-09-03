{{-- Plantilla base del contrato. Se renderiza una sola vez, al generarlo: a
     partir de ahí el texto vive en el propio contrato y se edita ahí, para que
     lo firmado no cambie cuando cambien los precios. --}}
@php
    $agency = config('app.name');
@endphp
CONTRATO DE PRESTACIÓN DE SERVICIOS

Folio: {{ $number }}

Entre {{ $agency }} (en adelante, "el prestador") y {{ $client->company_name ?? $client->name }} (en adelante, "el cliente"){{ $contact ? ', representado por '.$contact->name : '' }}, se celebra el presente contrato conforme a lo siguiente.

PRIMERA. OBJETO

El prestador se obliga a prestar al cliente los servicios que se enlistan a continuación, en los términos y con la periodicidad que en cada uno se indica:

@foreach ($services as $index => $service)
{{ $index + 1 }}. {{ $service->name }} — {{ $service->billing_frequency->label() }} — {{ number_format((float) $service->amount, 2) }} {{ $service->currency }}
@if ($service->description)
   {{ $service->description }}
@endif
@endforeach

@if ($items->isNotEmpty())
La prestación incluye los siguientes entregables:

@foreach ($items as $item)
- {{ $item->description }}{{ $item->due_date ? ' (previsto para el '.$item->due_date->format('d/m/Y').')' : '' }}
@endforeach
@endif

SEGUNDA. VIGENCIA

@if ($endsOn)
El presente contrato inicia su vigencia el {{ $startsOn->format('d/m/Y') }} y concluye el {{ $endsOn->format('d/m/Y') }}.
@else
El presente contrato inicia su vigencia el {{ $startsOn->format('d/m/Y') }} y permanecerá vigente hasta que cualquiera de las partes lo dé por terminado con aviso previo de treinta días naturales.
@endif

TERCERA. CONTRAPRESTACIÓN

@if ($recurringTotal > 0)
El cliente pagará por los servicios recurrentes la cantidad de {{ number_format($recurringTotal, 2) }} {{ $currency }} por periodo, conforme a la periodicidad señalada en la cláusula primera.
@endif
@if ($oneTimeTotal > 0)
Por los trabajos de pago único, el cliente pagará la cantidad de {{ number_format($oneTimeTotal, 2) }} {{ $currency }}.
@endif

Los pagos se realizarán dentro de los diez días naturales siguientes a la fecha de cada cobro. Los montos señalados no incluyen impuestos.

CUARTA. OBLIGACIONES DEL CLIENTE

El cliente se obliga a proporcionar oportunamente la información, los materiales y los accesos necesarios para la prestación de los servicios, así como a designar a una persona responsable de las autorizaciones que el trabajo requiera.

QUINTA. ACCESOS Y CONFIDENCIALIDAD

Las credenciales y accesos que el cliente proporcione al prestador se utilizarán exclusivamente para la prestación de los servicios contratados y se conservarán de forma cifrada. Ambas partes se obligan a guardar confidencialidad sobre la información que reciban de la otra con motivo de este contrato.

SEXTA. TITULARIDAD

Los dominios, cuentas y licencias contratados a nombre del cliente son de su propiedad. A la terminación de este contrato, el prestador entregará los accesos que administre y colaborará en su transferencia.

SÉPTIMA. TERMINACIÓN

Cualquiera de las partes podrá dar por terminado este contrato mediante aviso por escrito con treinta días naturales de anticipación, sin perjuicio de los servicios ya devengados a esa fecha, que deberán cubrirse.

Se firma de conformidad el {{ $startsOn->format('d/m/Y') }}.


_______________________________          _______________________________
{{ $agency }}                            {{ $client->company_name ?? $client->name }}
El prestador                             El cliente
