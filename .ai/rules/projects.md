---
paths:
  - 'app/{Actions/Projects/CreateProjectFromTemplate.php,Livewire/ServicesPanel.php,Enums/ServiceCategory.php}'
---

# Projects

## Hosting, SSL, dominio y correo nunca cuelgan de un proyecto
`ServiceCategory::belongsToDomain()` (Hosting, Ssl, Domain, Email) marca costos del dominio, no trabajo de proyecto: viven mientras el dominio exista, no mientras el proyecto esté abierto. `CreateProjectFromTemplate` crea esas categorías con `project_id = null` aunque nazcan de la plantilla de un proyecto Web (solo el resto, como Website, sí se cuelga del proyecto). `ServicesPanel` oculta esas categorías de las opciones cuando el panel vive en el detalle de un proyecto, y `saveService()` las rechaza en el servidor por si el valor llega manipulado. No vuelvas a dejar que la plantilla o el formulario les asignen un proyecto.
