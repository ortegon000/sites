---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Los paneles reciben el cliente; solo los de dinero saben de proyectos
`ServicesPanel` y `ChargesPanel` montan con `client` y, opcionalmente, `project`: con proyecto acotan a ese trabajo y viven en su detalle; sin él listan lo del cliente. `DomainsPanel`, `CampaignsPanel`, `QuotesPanel`, `ContractsPanel`, `ProjectsPanel` y `RenewalsPanel` montan solo con `client` y viven en su ficha. Antes de agregar una tarjeta a una página, revisa si uno de estos paneles ya la cubre en vez de duplicar la pantalla.

Autorizan siempre contra el cliente (`Gate::authorize('view'|'update', $this->client)`), y buscan los registros dentro del alcance del panel (`findService`, `findCharge`, `findCampaign`), porque el id llega del navegador: sin ese filtro se podría tocar el registro de otro cliente.

`services.project_id` es la única llave a proyecto que queda, y es nullable: `client_id` es el requerido. Dominios y campañas ya no tienen `project_id` —cuelgan solo del cliente—, así que no asumas que algo del cliente pasa por un proyecto.
