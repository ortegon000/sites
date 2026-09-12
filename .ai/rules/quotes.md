---
paths:
  - 'app/{Actions/Quotes/AcceptQuote.php,Livewire/QuotesPanel.php,Models/Quote.php,Models/QuoteLineItem.php}'
---

# Quotes

## Una cotización vive en renglones (QuoteLineItem), no en un monto único
`Quote` ya no tiene `category`, `billing_frequency`, `amount` ni `service_id`: esos campos viven por renglón en `QuoteLineItem` (`quote->lineItems()`), porque una propuesta de agencia normalmente mezcla conceptos (diseño de una vez + hosting anual). El monto de una cotización se calcula sumando sus renglones (usa `withSum('lineItems as amount_total', 'amount')` en listados para evitar N+1, no un accessor `amount` en `Quote`).

`AcceptQuote` crea una línea cobrable (`Service`) por cada renglón. El proyecto se abre solo si `quote->is_project` es true Y al menos un renglón no es de categoría de dominio (hosting/SSL/dominio/correo); si todos los renglones son de dominio, el interruptor "Es un proyecto" no tiene efecto (no hay UI que lo apague automáticamente como antes con la categoría única — la validación real vive en `AcceptQuote::handle()`, no en el formulario). Los renglones de categoría de dominio nunca cuelgan del proyecto aunque exista uno.

Al editar una cotización desde `QuotesPanel::syncLineItems()`, un renglón que ya tiene `service_id` (ya generó su línea cobrable) nunca se borra aunque se quite del formulario — se conserva como constancia.
