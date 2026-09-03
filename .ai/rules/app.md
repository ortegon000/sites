---
paths:
  - 'app/**'
---

# App

## `service->project` puede ser nulo: nunca lo encadenes
Desde la Fase 11, `services.project_id` es nullable y `client_id` es el requerido: una línea suelta cobrable cuelga del cliente. Cualquier código que toque `$charge->service->project` o `$service->project` debe tratarlo como opcional.

Esto ya rompió tres veces en producción de desarrollo: las notificaciones de cobro (`ChargeDueSoonNotification`/`ChargeOverdueNotification`), la corrida diaria `charges:process` y la tabla de próximos cobros del dashboard, que devolvía 500 con `route('projects.show', null)`.

La regla práctica: para nombrar al dueño de un cobro o de una línea, ancla en `$service->client` —que siempre existe— y muestra el proyecto solo cuando lo hay. Al escribir un test de algo que recorra servicios, incluye un caso con `Service::factory()->standalone()`.
