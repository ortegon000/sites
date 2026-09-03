---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Los paneles reciben el cliente y opcionalmente el proyecto
`ServicesPanel`, `ChargesPanel`, `DomainsPanel` y `CampaignsPanel` son tarjetas reutilizables que montan con `client` y, opcionalmente, `project`. Con proyecto acotan a ese trabajo y viven en su detalle; sin él listan lo del cliente y viven en su ficha. Antes de agregar una tarjeta a una página, revisa si uno de estos paneles ya la cubre en vez de duplicar la pantalla.

Autorizan siempre contra el cliente (`Gate::authorize('view'|'update', $this->client)`), y buscan los registros dentro del alcance del panel (`findService`, `findCharge`, `findCampaign`), porque el id llega del navegador: sin ese filtro se podría tocar el registro de otro cliente.

El proyecto es opcional en el modelo: `services.project_id` y `ad_campaigns.project_id` son nullable y `client_id` es el requerido. No asumas que una línea cobrable tiene proyecto.
