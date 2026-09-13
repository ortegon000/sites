---
paths:
  - 'app/{Actions/Quotes/AcceptQuote.php,Livewire/QuotesPanel.php,Models/Quote.php,Models/QuoteLineItem.php}'
---

# Quotes

## Una cotización vive en renglones (QuoteLineItem), no en un monto único
`Quote` ya no tiene `category`, `billing_frequency`, `amount` ni `service_id`: esos campos viven por renglón en `QuoteLineItem` (`quote->lineItems()`), porque una propuesta de agencia normalmente mezcla conceptos (diseño de una vez + hosting anual). El monto de una cotización se calcula sumando sus renglones (usa `withSum('lineItems as amount_total', 'amount')` en listados para evitar N+1, no un accessor `amount` en `Quote`).

`AcceptQuote` crea una línea cobrable (`Service`) por cada renglón. El proyecto se abre solo si `quote->is_project` es true Y al menos un renglón no es de categoría de dominio (hosting/SSL/dominio/correo); si todos los renglones son de dominio, elegir "Proyecto" al aceptar no tiene efecto (la validación real vive en `AcceptQuote::handle()`, no en el formulario). Los renglones de categoría de dominio nunca cuelgan del proyecto aunque exista uno.

`is_project` no se captura ni se edita en el formulario de la cotización (`saveQuote()` no lo toca, ni al crear ni al editar): se pregunta únicamente en `openAcceptModal()`/`confirmAccept()`, con el radio "Línea suelta"/"Proyecto", porque aceptar es el momento en que de verdad se sabe si el trabajo amerita uno. Por eso editar una cotización ya aceptada no le resetea `is_project` a `false`.

Al editar una cotización desde `QuotesPanel::syncLineItems()`, un renglón que ya tiene `service_id` (ya generó su línea cobrable) nunca se borra aunque se quite del formulario — se conserva como constancia.

## Enviar, aceptar y rechazar son solo banderas de estatus, y todas se pueden deshacer
`SendQuote`/`AcceptQuote`/`RejectQuote` no generan ningún documento ni notifican al cliente: son marcas manuales de que eso ya pasó por fuera del sistema. Por eso cada una tiene su reverso en `QuotesPanel` (`undoSend`, `undoReject`, `undoAccept`) para corregir un clic equivocado, sin acciones ni tablas nuevas para lo trivial (enviar/rechazar se revierten con un `update()` inline; solo aceptar, que sí crea registros, tiene su propia Action `UndoAcceptQuote`).

`UndoAcceptQuote` se niega a deshacer si algún renglón ya tiene un cobro con abono (mismo criterio que `Service::canBeDeleted()`), para no perder esa constancia. Si puede, borra las líneas cobrables de todos los renglones y regresa el estatus a `Enviada` o `Borrador` según si `sent_at` estaba lleno. El proyecto que haya abierto la aceptación (`quote->is_project` true) se desliga (`project_id = null`) pero no se borra —queda vacío para que se borre a mano si ya no aplica—; si la cotización ya vivía dentro de un proyecto desde que se capturó, ese vínculo no se toca. Tampoco revierte el estatus del cliente (p. ej. un prospecto ganado), porque puede depender de más que esta sola cotización.

Deshacer una aceptación dispara el evento `quote-undone` -además de `quote-accepted`- para que `ChargesPanel`, `ProjectsPanel` y `ServicesPanel` refresquen lo que acaban de perder, igual que se enteran cuando se acepta.

## Copiar una cotización copia solo su enlace público
`ManagesQuoteActions::quotePublicUrl()` arma el enlace (ver `.ai/rules/pages-quotes.md`); el botón de copiar solo pone ese enlace en el portapapeles, sin texto alrededor -quien lo pega decide cómo presentarlo-. El copiado ocurre en el navegador vía Alpine (`navigator.clipboard.writeText`, con la URL inyectada por `@js()`); `markCopied()` solo confirma con un toast, no participa en el copiado en sí.
