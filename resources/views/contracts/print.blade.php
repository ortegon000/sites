<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $contract->number }} — {{ $contract->client->name }}</title>
        <style>
            :root { color-scheme: light; }
            body {
                margin: 0;
                background: #f4f4f5;
                color: #18181b;
                font-family: ui-serif, Georgia, "Times New Roman", serif;
                line-height: 1.6;
            }
            .sheet {
                max-width: 46rem;
                margin: 2rem auto;
                padding: 3.5rem 4rem;
                background: #fff;
                box-shadow: 0 1px 3px rgb(0 0 0 / 12%);
            }
            .toolbar {
                max-width: 46rem;
                margin: 1.5rem auto 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 0.875rem;
            }
            .toolbar a, .toolbar button {
                font: inherit;
                color: #3f3f46;
                background: #fff;
                border: 1px solid #d4d4d8;
                border-radius: 0.375rem;
                padding: 0.4rem 0.8rem;
                cursor: pointer;
                text-decoration: none;
            }
            .meta {
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 0.75rem;
                color: #71717a;
                border-bottom: 1px solid #e4e4e7;
                padding-bottom: 0.75rem;
                margin-bottom: 2rem;
                display: flex;
                justify-content: space-between;
                gap: 1rem;
            }
            pre {
                margin: 0;
                font: inherit;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .draft-note {
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 0.75rem;
                color: #a16207;
                background: #fef9c3;
                border-radius: 0.375rem;
                padding: 0.5rem 0.75rem;
                margin-bottom: 2rem;
            }
            @media print {
                body { background: #fff; }
                .toolbar { display: none; }
                .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; }
                .draft-note { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <a href="{{ route('clients.show', $contract->client) }}">{{ __('Volver al cliente') }}</a>
            <button type="button" onclick="window.print()">{{ __('Imprimir o guardar como PDF') }}</button>
        </div>

        <div class="sheet">
            <div class="meta">
                <span>{{ $contract->client->company_name ?? $contract->client->name }}</span>
                <span>{{ $contract->number }} · {{ $contract->status->label() }}</span>
            </div>

            @if ($contract->status !== \App\Enums\ContractStatus::Firmado)
                <p class="draft-note">
                    {{ __('Este contrato todavía no está firmado. Revísalo —de preferencia con tu abogado— antes de enviarlo: la plantilla es un punto de partida, no asesoría legal.') }}
                </p>
            @endif

            <pre>{{ $contract->body }}</pre>
        </div>
    </body>
</html>
