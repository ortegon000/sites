---
paths:
  - 'app/{Actions/Projects/CreateProjectFromTemplate.php,Actions/Quotes/AcceptQuote.php,Livewire/ServicesPanel.php,Livewire/QuotesPanel.php,Enums/ServiceCategory.php}'
---

# Projects

## Hosting, SSL, dominio y correo nunca cuelgan de un proyecto
`ServiceCategory::belongsToDomain()` (Hosting, Ssl, Domain, Email) marca costos del dominio, no trabajo de proyecto: viven mientras el dominio exista, no mientras el proyecto esté abierto. `CreateProjectFromTemplate` crea esas categorías con `project_id = null` aunque nazcan de la plantilla de un proyecto Web (solo el resto, como Website, sí se cuelga del proyecto). `AcceptQuote` hace lo mismo al aceptar una cotización marcada como proyecto. `ServicesPanel` y `QuotesPanel` ocultan esas categorías de las opciones cuando el panel vive en el detalle de un proyecto, y validan lo mismo en el servidor por si el valor llega manipulado; `QuotesPanel` además apaga el interruptor "Es un proyecto" en cuanto se elige una de esas categorías, porque ni siquiera fuera de un proyecto tiene sentido que abran uno. No vuelvas a dejar que una plantilla o un formulario les asignen un proyecto.
