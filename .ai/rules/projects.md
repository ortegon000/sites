---
paths:
  - 'app/{Actions/Projects/CreateProjectFromTemplate.php,Actions/Quotes/AcceptQuote.php,Livewire/ServicesPanel.php,Livewire/QuotesPanel.php,Enums/ServiceCategory.php}'
---

# Projects

## Hosting, SSL, dominio y correo nunca cuelgan de un proyecto
`ServiceCategory::belongsToDomain()` (Hosting, Ssl, Domain, Email) marca costos del dominio, no trabajo de proyecto: viven mientras el dominio exista, no mientras el proyecto esté abierto. `CreateProjectFromTemplate` crea esas categorías con `project_id = null` aunque nazcan de la plantilla de un proyecto Web (solo el resto, como Website, sí se cuelga del proyecto). `AcceptQuote` hace lo mismo al aceptar una cotización marcada como proyecto: cada renglón (`QuoteLineItem`) se evalúa por su propia categoría, así que una cotización puede mezclar renglones de dominio con renglones de proyecto en la misma propuesta. `ServicesPanel` y `QuotesPanel` ocultan esas categorías de las opciones cuando el panel vive en el detalle de un proyecto, y validan lo mismo en el servidor por si el valor llega manipulado. No vuelvas a dejar que una plantilla o un formulario les asignen un proyecto.

Ver `.ai/rules/quotes.md` para cómo `AcceptQuote` decide si abre proyecto cuando la cotización tiene varios renglones.
