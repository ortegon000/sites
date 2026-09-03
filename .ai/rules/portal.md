---
paths:
  - 'resources/views/pages/portal/**'
---

# Portal

## El portal es del cliente, no de sus proyectos
El portal entra por `portal.services.index` (sus líneas cobrables) y `portal.charges.index` (sus cobros), consultando siempre `auth()->user()->clients()`: una persona con varias empresas las ve todas con un solo acceso. El detalle de proyecto (`portal.projects.show`) sigue existiendo como contexto de una línea, pero ya no hay índice de proyectos ni nada que dependa de que exista uno.

La razón: la mayoría de los clientes no tiene proyecto abierto —viven de hosting, dominio y renovaciones—, y cuando el portal colgaba de los proyectos entraban a una pantalla vacía aunque se les estuviera cobrando cada año.

Al agregar algo al portal, cuélgalo del cliente. Si lo cuelgas del proyecto, lo estás escondiendo de la mayoría.
