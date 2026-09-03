# CRM interno para agencia — Plan de implementación

> Copia de trabajo dentro del repo del plan original (`~/.claude/plans/linked-fluttering-shamir.md`), para que cualquier sesión de Claude Code que abras en este proyecto pueda encontrarlo y continuar. Si retomas esto en una conversación nueva, dile a Claude: **"Continúa con el plan en CRM_PLAN.md"**.

## Para retomar en otra conversación

**Dónde estamos.** Fases 0 a 11 implementadas (la 5 sigue a medias: falta un driver de correo real). El modelo cubre clientes y contactos, dominios con buzones y accesos, licencias, campañas, líneas cobrables con o sin proyecto, cobros con abonos, agencias, portal y notificaciones. El libro de hosting del dueño ya está importado: 19 clientes, 19 dominios, 59 buzones, 23 accesos.

**Qué sigue.** La **Fase 12 (renovaciones)**, descrita más abajo: el tablero unificado de caducidades y el aviso al cliente, que es lo que motivó todo esto. Ojo con el pendiente del dueño: sin fechas de renovación capturadas, ese tablero no tendrá nada que avisar.

**Antes de empezar, leer** la sección "Modelo objetivo y hoja de ruta (Fases 9+)": explica la distinción entre activo, trabajo y servicio recurrente, y por qué el modelo actual hay que aflojarlo en dos puntos —el proyecto obligatorio en los servicios y el cobro binario— en vez de rehacerlo.

**Pendientes del dueño, no de código**: renombrar los 19 clientes importados (quedaron derivados del dominio, como "Geeaguasresiduales"), capturar las fechas de renovación que su hoja no traía en ninguna fila, y rotar las contraseñas que estuvieron en texto plano en el archivo.

**Verificación en cada fase**: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` (nivel 7), `php artisan test --compact`, y revisión en `https://sites.test`. Al cierre de la Fase 11: 202 tests, 200 pasan, 2 se saltan.

## Estado actual

- ✅ **Fase 0 — Roles y fundamento de auth**: completa.
- ✅ **Fase 1 — CRM de clientes y prospectos**: completa.
- ✅ **Fase 2 — Proyectos → Servicios → Cobros + recordatorios**: completa.
- ✅ **Fase 3 — Agencias colaboradoras**: completa.
- ✅ **Fase 4 — Portal de clientes**: completa (4a proyectos/cobros y 4b correos propios, ambas de solo lectura).
- 🔶 **Fase 5 — Aprovisionamiento de correo**: andamiaje completo (interfaz de driver, tablas, CRUD de proveedores, altas/bajas/cambio de contraseña de cuentas) con un driver simulado; falta conectar un driver real (MXroute primero) cuando haya credenciales.
- ✅ **Fase 6 — Dashboard interno**: completa (KPIs financieros y próximos cobros/actividad reciente para admin/staff, resumen de proyectos asignados sin datos financieros para collaborator).
- ✅ **Fase 7 — Dominios, tipos de proyecto y campañas de ads**: completa. Introduce la tabla `domains` (dueño: el cliente), mueve las cuentas de correo de `clients` a `domains`, agrega `ProjectType` como plantilla, `ServiceCategory`, frecuencias trimestral/semestral, proveedor de correo `manual` con contraseñas cifradas, importación de buzones existentes y campañas de ads.
- ✅ **Fase 8 — Contactos como entidad propia**: completa. Separa a la persona de la empresa: `contacts` con pivot `client_contact`, para que un dueño de varias empresas se escriba una sola vez y entre al portal con un solo acceso.
- ✅ **Fase 10 — Abonos, cobros editables y agencias**: completa. El cobro deja de ser binario (abonos, estatus derivado y restante), se pueden editar monto, fecha y concepto, y la agencia pasa a declarar a quién se le factura, con filtros y reporte de cobrado/por cobrar.
- ✅ **Fase 11 — Líneas sueltas, subtareas y campañas del cliente**: completa. El proyecto deja de ser obligatorio para cobrar, la ficha del cliente gana captura rápida, los servicios llevan subtareas, aparece la frecuencia quincenal y el menú de Proyectos cede el paso a "Trabajos y cobros".
- 🔶 **Fases 9–14 — Centralizar los tres Excel**: de la 9 a la 11 completas, el resto planeadas. Ver "Modelo objetivo y hoja de ruta" más abajo: activos (credenciales y licencias), abonos y pagos parciales, líneas cobrables sin proyecto con subtareas, renovaciones con aviso al cliente, cotizaciones y contratos.

Verificación al cierre de Fase 0+1: `php artisan test --compact` → 40 tests (38 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, y flujo probado manualmente en `https://sites.test`.

Verificación al cierre de Fase 2: `php artisan test --compact` → 58 tests (56 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` y `php artisan charges:process` corridos manualmente contra `https://sites.test` sin errores.

Verificación al cierre de Fase 3: `php artisan test --compact` → 67 tests (65 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo completo verificado en `https://sites.test` (alta de agencia, asociación a proyecto con cada `billing_direction`, confirmación de que `collaborator` no ve la tarjeta).

Verificación al cierre de Fase 4a: `php artisan test --compact` → 81 tests (79 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo completo verificado en `https://sites.test` (login como `cliente@example.com` redirige a `/portal`, lista solo sus propios proyectos, detalle de proyecto muestra servicios/cobros propios sin agencias ni equipo asignado, acceso a `/portal/proyectos/{id}` de otro cliente devuelve 403, `/dashboard` redirige a `/portal`, logout funciona).

Verificación al cierre del andamiaje de Fase 5: `php artisan test --compact` → 90 tests (88 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo verificado en `https://sites.test` (admin ve/crea/elimina proveedores en `/proveedores-correo`, staff no puede entrar ahí, tarjeta "Cuentas de correo" en el detalle de cliente permite crear una cuenta nueva y cambiar su contraseña sin errores; la eliminación con confirmación nativa del navegador quedó verificada solo por el test automatizado, ya que el diálogo `confirm()` nativo no puede pilotarse desde la automatización de navegador usada en esta sesión).

Verificación al cierre de Fase 4b: `php artisan test --compact` → 97 tests (95 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo verificado en `https://sites.test` (login como `cliente@example.com` → `/portal/correo` muestra su propia cuenta de correo con la configuración IMAP/SMTP simulada, la barra "Proyectos / Correo" del portal navega entre ambas vistas).

Verificación al cierre de Fase 6: `php artisan test --compact` → 101 tests (99 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo verificado en `https://sites.test` con los 3 roles (`test@example.com` ve KPIs de cobros pendientes/vencidos, proyectos activos, prospectos abiertos, tabla de próximos cobros y actividad reciente; `colaborador@example.com` ve solo "Mis proyectos activos" y su tabla de proyectos asignados, sin ninguna tarjeta financiera; `cliente@example.com` sigue redirigiendo a `/portal` sin pasar por el dashboard interno).

## Contexto

La app es un Laravel 13 recién creado a partir de `laravel/livewire-starter-kit` (Livewire 4 nativo, Flux UI, Fortify con 2FA/passkeys). El objetivo es convertirlo en la herramienta interna de la agencia para: llevar CRM de clientes/prospectos, gestionar proyectos (recurrentes y de un solo evento), dar seguimiento a cobros recurrentes (hosting, dominio, mantenimiento, ads, pagos a plazos) con recordatorios, llevar registro de agencias colaboradoras (facturación bidireccional), y —en una fase posterior— administrar altas/bajas de cuentas de correo de clientes vía APIs de cPanel/Hostinger/MXroute, con un portal limitado para que los clientes vean sus propios proyectos, cobros y correos.

Los requisitos se confirmaron con el dueño de la agencia por rondas de preguntas (roles, alcance de cobros sin procesar pagos ni CFDI, multi-moneda, notificaciones solo por email + panel interno, sin kanban de tareas ni pipeline de ventas por ahora).

## Decisiones confirmadas por el usuario

- **Roles**: enum + Gate/Policy nativo de Laravel (no `spatie/laravel-permission`) — 4 roles fijos y no hay necesidad de permisos granulares configurables.
- **Registro público**: se cierra `/register`. Los usuarios (staff, colaboradores, clientes) se crean desde un panel de administración por el admin, no por auto-registro.
- **Carpetas nuevas**: aprobado crear `app/Enums`, `app/Policies`, `app/Notifications` (y más adelante `app/Services/EmailProvisioning`).
- **Cobros**: solo seguimiento y recordatorios, sin procesar pagos ni timbrado fiscal (CFDI/SAT) — comprobante interno simple.
- **Notificaciones**: solo email + notificación interna en panel (bell), nada de WhatsApp por ahora.
- **Moneda**: multi-moneda (campo `currency` por registro, sin conversión de tipo de cambio en este MVP).
- **Agencias colaboradoras**: relación bidireccional (a veces les facturamos, a veces nos facturan) — módulo separado de `clients`.
- **Sin kanban de tareas ni pipeline de ventas** en esta fase — solo campo de estatus simple para prospectos.

---

## Fase 0 — Roles y fundamento de auth ✅

1. **Enum de rol**: `app/Enums/UserRole.php`, backed string enum: `Admin='admin'`, `Staff='staff'`, `Collaborator='collaborator'`, `Client='client'`.
2. **Migraciones**: `add_role_to_users_table` (columna `role` string, default `staff`) y `add_client_id_to_users_table` (FK nullable → `clients.id`, `nullOnDelete`, agregada después de crear `clients`).
3. **`app/Models/User.php`**: `role` casteado al enum en `casts()`; helpers `isAdmin()`, `isStaff()`, `isCollaborator()`, `isClient()`. `role`/`client_id` **no** están en `#[Fillable(...)]` — la asignación de rol va siempre por código explícito de admin.
4. **Registro público cerrado**: `Features::registration()` removido de `config/fortify.php`, `Fortify::registerView(...)` removido de `FortifyServiceProvider`, link "Sign up" quitado del login. `/register` responde 404.
5. **Policies**: `app/Policies/ClientPolicy.php` creado (auto-descubierta, sin registro manual necesario en Laravel 13).
6. **Middleware de rol**: `app/Http/Middleware/EnsureUserHasRole.php`, alias `role` registrado en `bootstrap/app.php`.
7. **Seeder**: el usuario de prueba (`test@example.com` / `password`) ahora se crea con rol `admin` vía `UserFactory::admin()`.

## Fase 1 — CRM de clientes y prospectos ✅

### Modelo de datos (implementado)

**`clients`** (tabla unificada de prospectos + clientes; la conversión prospecto→cliente es un cambio de `status`, no una tabla distinta): `type` (enum `ClientType`: prospect/client), `status` (enum `ClientStatus`), `name`, `company_name`, `contact_name`, `email`, `phone`, `source`, `assigned_to_user_id` (FK users), `currency` (char 3, default MXN), `won_at`, `lost_reason`, timestamps, soft deletes.

**`client_notes`** (bitácora): `client_id`, `user_id` (autor, nullable), `type` (enum `ClientNoteType`: note/call/email/status_change), `body`, timestamps.

### Archivos clave ya creados

- `app/Models/Client.php`, `app/Models/ClientNote.php`
- `app/Policies/ClientPolicy.php`
- `app/Actions/Clients/ChangeClientStatus.php` — al marcar un prospecto como "Ganado" lo convierte automáticamente a `type=client`, fija `won_at`, y registra una nota `status_change`.
- `routes/crm.php` (incluido desde `web.php`): `clients.index` (`/clientes`), `prospects.index` (`/prospectos`, mismo componente con `type` fijado en `mount()`), `clients.show` (`/clientes/{client}`).
- `resources/views/pages/clients/⚡index.blade.php` — listado + modal de alta/edición (Flux modal).
- `resources/views/pages/clients/⚡show.blade.php` — detalle, bitácora de notas, cambio de estatus.
- Sidebar (`resources/views/layouts/app/sidebar.blade.php`): grupo "CRM" con Clientes/Prospectos, visible solo para admin/staff.
- Tests: `tests/Feature/Clients/ClientManagementTest.php` (7 tests: acceso por rol, CRUD, políticas, conversión prospecto→cliente).

### Seeders y factories

- `database/factories/`: `UserFactory` (estados `admin()`, `staff()`, `collaborator()`, `client(?Client $client)`), `ClientFactory` (estados `prospect()`, `client()`), `ClientNoteFactory` (estados `call()`, `email()`, `statusChange()`).
- `database/seeders/`: `ClientSeeder` (1 "Cliente Demo" + 4 clientes + 6 prospectos), `UserSeeder` (1 usuario por rol, el de rol `client` vinculado a "Cliente Demo"), `ClientNoteSeeder` (1–3 notas aleatorias por cliente/prospecto, autoría de admin/staff), orquestados desde `DatabaseSeeder` con `$this->call([...])`.
- Usuarios de prueba tras `php artisan migrate:fresh --seed` (password `password` para todos): `test@example.com` (admin), `staff@example.com` (staff), `colaborador@example.com` (collaborator), `cliente@example.com` (client, portal de "Cliente Demo").

### Nota de una corrección durante la implementación

El tipo (`prospect`/`client`) inicialmente se calculaba con un método `#[Computed]` que leía `request()->routeIs('prospects.index')`. Esto se rompía en las peticiones AJAX subsecuentes de Livewire (que no pasan por la ruta original `/prospectos`), haciendo que el formulario de creación mostrara los estatus equivocados después de la primera interacción. Se corrigió fijando `$this->type` una sola vez como propiedad pública en `mount()`, que sí persiste correctamente entre peticiones de Livewire.

## Fase 2 — Proyectos → Servicios → Cobros + recordatorios ✅

### Modelo de datos (implementado)

**`projects`**: `client_id` (FK clients, cascade), `name`, `description`, `status` (enum `ProjectStatus`: activo/pausado/completado/cancelado), `started_at`, `ended_at`, timestamps, soft deletes.

**`project_user`** (pivot, clave primaria compuesta): habilita que un `collaborator` solo vea/acceda a los proyectos donde está asignado; admin/staff ven todos.

**`services`**: `project_id`, `name`, `description`, `billing_frequency` (enum `ServiceBillingFrequency`: one_time/monthly/annual/installment), `amount` (monto por cada ocurrencia de cobro, no total del contrato), `currency`, `status` (enum `ServiceStatus`), `starts_on`, `next_charge_date` (solo monthly/annual), `installments_count` (solo installment).

**`service_installments`**: `service_id`, `installment_number`, `amount`, `due_date` — generadas automáticamente (mensuales, monto igual) al crear un servicio `installment`.

**`charges`**: `service_id`, `service_installment_id` (nullable), `amount`, `currency`, `status` (enum `ChargeStatus`: pendiente/pagado/vencido), `due_date`, `paid_at`, `due_soon_notified_at`, `overdue_notified_at` (estos dos evitan reenviar el mismo recordatorio).

### Archivos clave ya creados

- `app/Models/{Project,Service,ServiceInstallment,Charge}.php`; `User::projects()` (inversa de `project_user`).
- `app/Policies/ProjectPolicy.php` — `viewAny`/`view` permiten también a `collaborator` (view solo si está asignado vía `project_user`); create/update/delete igual que `ClientPolicy`.
- `app/Actions/Services/CreateServiceWithSchedule.php` — crea el servicio, genera las cuotas si es `installment`, fija `next_charge_date` si es recurrente, y genera el primer cobro de inmediato si ya corresponde (sin esperar la corrida diaria).
- `app/Actions/Charges/{GenerateScheduledCharges,MarkOverdueCharges,SendChargeReminders,MarkChargeAsPaid}.php`.
- `app/Console/Commands/ProcessScheduledCharges.php` (`charges:process`), programado en `routes/console.php` con `Schedule::command('charges:process')->dailyAt('07:00')`.
- `app/Notifications/{ChargeDueSoonNotification,ChargeOverdueNotification}.php` — canales `mail`+`database`, sin `ShouldQueue` (se envían síncronas desde el comando, sin depender de un worker de colas). Destinatarios: todos los `admin` + los `staff` asignados al proyecto vía `project_user`.
- `app/Livewire/NotificationsBell.php` + `resources/views/livewire/notifications-bell.blade.php` — componente de clase (no página) embebido en `sidebar.blade.php`, visible solo para admin/staff.
- `routes/crm.php`: grupo `role:admin,staff,collaborator` para `/proyectos` y `/proyectos/{project}`.
- `resources/views/pages/projects/⚡index.blade.php` y `⚡show.blade.php` — listado con filtro por estatus (y scope automático a proyectos asignados si el usuario es `collaborator`), detalle con equipo asignado, servicios y cobros gestionados inline (mismo patrón que `ClientNote` en Fase 1: sin policy propia, autorizado contra `update`/`view` del `Project` padre). Los `collaborator` no ven montos: la columna "Monto" de la tabla de servicios y toda la tarjeta "Cobros" están ocultas en la vista para ese rol (solo admin/staff ven ambas), conforme a la decisión de "sin acceso a datos financieros" para colaboradores ya confirmada por el dueño de la agencia.

### Seeders y factories

- `database/factories/{Project,Service,ServiceInstallment,Charge}Factory.php` (estados `oneTime()/monthly()/annual()/installment()` en `ServiceFactory`; `pending()/paid()/overdue()` en `ChargeFactory`).
- `database/seeders/ProjectSeeder.php`: 3 proyectos (uno por cada cliente ya sembrado) con servicios variados y cobros ya en distintos estados (pendiente/pagado/vencido), para ver la UI poblada sin correr el comando. Registrado en `DatabaseSeeder` después de `ClientNoteSeeder`.
- Tests: `tests/Feature/Projects/{ProjectManagementTest,ServiceSchedulingTest,ChargeProcessingTest}.php` (18 tests: acceso por rol, scope de colaborador, ocultamiento de montos/cobros para `collaborator`, CRUD de proyecto, alta de servicio con cada `billing_frequency`, generación/vencimiento/recordatorio de cobros vía el comando, sin duplicar notificaciones en corridas repetidas).

### Nota de una corrección durante la implementación

El modelo `Charge` originalmente no incluía `paid_at`, `due_soon_notified_at` ni `overdue_notified_at` en su atributo `#[Fillable(...)]`. Como Laravel descarta en silencio los campos no-fillable en `update()` (sin lanzar excepción), `MarkChargeAsPaid` y `SendChargeReminders` ejecutaban sus `update()` sin error pero sin persistir esos campos — el síntoma fue que el comando `charges:process` reenviaba el mismo recordatorio en cada corrida porque `due_soon_notified_at`/`overdue_notified_at` nunca quedaban guardados. Se corrigió agregando los tres campos al `#[Fillable(...)]` del modelo.

Por separado, al actualizar esta documentación se detectó que la vista de detalle de proyecto mostraba montos de servicios y la tarjeta "Cobros" completa a cualquier usuario con acceso al proyecto, incluyendo `collaborator` — contradiciendo la decisión "sin acceso a datos financieros" ya confirmada para ese rol (ver README, sección "Roles y accesos"). Se corrigió ocultando ambos elementos en la vista para `collaborator`, y se agregó una prueba (`ProjectManagementTest`) que verifica que no aparecen en el HTML renderizado.

## Fase 3 — Agencias colaboradoras ✅

### Modelo de datos (implementado)

**`agencies`**: `name`, `contact_name`, `email`, `phone`, `status` (enum `AgencyStatus`: activa/inactiva), timestamps, soft deletes.

**`agency_project`** (pivot, clave primaria compuesta `(agency_id, project_id)`): `billing_direction` (enum `AgencyBillingDirection`: we_invoice_them/they_invoice_us), `notes` (nullable, contexto libre — no estaba en el roadmap original pero se agregó como mínimo razonable para que el registro sea útil).

### Archivos clave ya creados

- `app/Models/Agency.php`; `Agency::projects()` / `Project::agencies()` — `belongsToMany` con `withPivot(['billing_direction', 'notes'])->withTimestamps()`.
- `app/Policies/AgencyPolicy.php` — copia exacta de `ClientPolicy` (solo admin/staff; delete solo admin). La relación `agency_project` no tiene policy propia: se gestiona autorizando contra `update`/`view` del `Project` padre, igual que `project_user`.
- `routes/crm.php`: `agencies.index` (`/agencias`) agregada al mismo grupo `role:admin,staff` que ya usan `clients.*`/`prospects.*`. Sin ruta `show` — la asociación a proyectos se gestiona desde el detalle del proyecto.
- `resources/views/pages/agencies/⚡index.blade.php` — CRUD simple (alta/edición/baja), calco de `clients/⚡index.blade.php` sin la distinción prospecto/cliente.
- `resources/views/pages/projects/⚡show.blade.php` — nueva tarjeta "Agencias colaboradoras" (formulario de asociación con `billing_direction` + notas, lista de agencias asociadas con botón para quitar), visible **solo para admin/staff** (mismo guard que ya usa "Cobros"): los `collaborator` no ven agencias ni direcciones de facturación, consistente con "sin acceso a datos financieros".
- Sidebar: "Agencias" agregado al grupo "CRM" existente, junto a Clientes/Prospectos.

### Seeders y factories

- `database/factories/AgencyFactory.php`.
- `database/seeders/AgencySeeder.php`: 3 agencias demo, 2 asociadas a los primeros dos proyectos ya sembrados (una con cada `billing_direction`). Registrado en `DatabaseSeeder` después de `ProjectSeeder`.
- Tests: `tests/Feature/Agencies/AgencyManagementTest.php` (7 tests: acceso por rol, CRUD, políticas) + 2 tests nuevos en `ProjectManagementTest.php` (asociar/quitar agencia con dirección de facturación desde el detalle del proyecto; `collaborator` no ve la tarjeta).

### Ajuste post-Fase 3: agencia por cliente (heredada a sus proyectos)

El dueño de la agencia pidió dos ajustes adicionales sobre el modelo de Fase 3:

1. **`clients.agency_id`** (FK nullable → `agencies`, `nullOnDelete`): un cliente puede llegar a través de una agencia colaboradora. `Client::agency()` / `Agency::clients()` (`belongsTo`/`hasMany`) y `Client::projects()` (`hasMany`, antes solo existía la relación inversa en `Project`).
2. **Herencia automática a proyectos**: `app/Actions/Clients/SyncClientAgencyToProjects.php` vincula (`agency_project`, sin tocar asociaciones ya existentes) la agencia del cliente a todos sus proyectos que aún no la tengan. Se invoca tanto al guardar un cliente (`clients/⚡index.blade.php`, cubre asignar/cambiar la agencia de un cliente con proyectos ya existentes) como al guardar un proyecto (`projects/⚡index.blade.php`, cubre proyectos nuevos). `agency_project.billing_direction` se volvió nullable (migración `make_billing_direction_nullable_on_agency_project_table`) porque la asociación heredada no adivina una dirección de facturación — queda pendiente de que staff la defina desde la tarjeta "Agencias colaboradoras" del proyecto (mismo formulario, `syncWithoutDetaching` sobre la misma agencia actualiza el pivot). La vista de proyecto muestra un badge "Heredada del cliente · falta definir facturación" mientras tanto.
3. **Prellenado de contacto**: en el formulario de cliente, seleccionar una agencia (`wire:model.live="agency_id"`, hook `updatedAgencyId()`) prellena `contact_name`/`email`/`phone` con los datos de la agencia — solo los campos vacíos, nunca sobrescribe un contacto directo ya capturado. Refleja que, trabajando vía agencia, normalmente no hay contacto directo con el cliente final.

Archivos: migraciones `2026_08_30_205742_add_agency_id_to_clients_table` y `2026_08_30_205743_make_billing_direction_nullable_on_agency_project_table`; `app/Actions/Clients/SyncClientAgencyToProjects.php`; `app/Models/{Client,Agency}.php`; `resources/views/pages/clients/⚡{index,show}.blade.php`, `resources/views/pages/projects/⚡{index,show}.blade.php`. Seeder: `AgencySeeder` ahora asigna la agencia "Northwind Digital" al cliente del tercer proyecto sembrado para demostrar la herencia retroactiva. Tests nuevos: 3 en `ClientManagementTest.php` (prellenado con y sin contacto previo, herencia a proyectos existentes) + 1 en `ProjectManagementTest.php` (herencia al crear un proyecto nuevo). Verificado con `php artisan test --compact` (71 tests, 69 pasan, 2 se saltan), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, y flujo de prellenado confirmado manualmente en `https://sites.test`.

## Fase 4a — Portal de clientes (proyectos y cobros de solo lectura) ✅

### Alcance

Portal de solo lectura para usuarios con rol `client`: ven únicamente los proyectos de su propio registro `Client` (vía `User::client_id`), y dentro de cada proyecto sus servicios y cobros — sin agencias colaboradoras, sin equipo asignado, sin ninguna acción de edición. No requirió modelo de datos nuevo (ya existían `User::client_id`/`client()` desde Fase 0).

### Archivos clave creados

- `resources/views/layouts/portal.blade.php` — layout independiente del layout interno (`layouts/app.blade.php`): sin sidebar ni grupos de navegación CRM/Proyectos, solo `flux:header` con logo y menú de usuario (Settings + Log out).
- `routes/portal.php` (incluido desde `web.php`): grupo `role:client` con `portal.projects.index` (`/portal`) y `portal.projects.show` (`/portal/proyectos/{project}`).
- `resources/views/pages/portal/projects/⚡index.blade.php` — lista los proyectos del cliente autenticado (`Project::where('client_id', auth()->user()->client_id)`), sin depender de `ProjectPolicy::viewAny()` (esa policy sigue siendo solo admin/staff/collaborator).
- `resources/views/pages/portal/projects/⚡show.blade.php` — detalle de un proyecto propio: datos generales, servicios (con monto) y cobros, calcado de `pages::projects.show` pero sin las tarjetas de "Equipo asignado" ni "Agencias colaboradoras" y sin botón "Marcar pagado". Ambos componentes usan `#[Layout('layouts::portal')]` para no heredar el layout interno por defecto.
- `app/Policies/ProjectPolicy.php` — `view()` ahora también autoriza a un `client` cuando `$user->client_id === $project->client_id`.
- `app/Http/Responses/PortalAwareLoginResponse.php` — implementación de `Laravel\Fortify\Contracts\LoginResponse` que redirige a `portal.projects.index` si el usuario autenticado `isClient()`, o al `fortify.home` de siempre en cualquier otro caso; enlazada en `FortifyServiceProvider::register()`.
- `routes/web.php` — la ruta `dashboard` ahora es un closure que redirige a `portal.projects.index` si el usuario es `client` (antes era una `Route::view` fija); esto cubre tanto el acceso directo a `/dashboard` como cualquier enlace viejo guardado.
- Tests: `tests/Feature/Portal/PortalProjectsTest.php` (6 tests: acceso por rol —incluye `client` sin `client_id` vinculado—, scope a proyectos propios, detalle con servicios/cobros, 403 al ver el proyecto de otro cliente) + 1 test en `tests/Feature/DashboardTest.php` (redirección a portal) + 1 test en `tests/Feature/Auth/AuthenticationTest.php` (login de `client` redirige a portal).

### Nota de diseño

No se reutilizó `pages::projects.show` con condicionales adicionales por rol (como sí se hizo para ocultar montos a `collaborator` en Fase 2) porque el portal necesita un layout completamente distinto (sin sidebar) y una fuente de datos distinta (proyectos del `Client` del usuario, no proyectos asignados por `project_user`); duplicar la vista fue más simple que ramificar layout+query+visibilidad de tarjetas en un solo componente.

## Fase 5 — Aprovisionamiento de correo (andamiaje con driver simulado) 🔶

### Decisión del usuario

Se confirmó con el dueño de la agencia construir primero todo el andamiaje (interfaz de driver, tablas, políticas, UI de administración y de altas/bajas) usando un driver simulado que no llama a ninguna API real, en vez de esperar a tener las credenciales de MXroute. Cuando esas credenciales estén disponibles, solo hace falta agregar la clase de driver real y ampliar `EmailProviderDriverType::implemented()` — el resto de la aplicación (acciones, policies, vistas) ya está construido contra la interfaz y no necesita cambios.

### Modelo de datos (implementado)

**`email_providers`**: `name`, `driver` (enum `EmailProviderDriverType`: `null`/`mxroute`/`cpanel`/`hostinger` — solo `null` tiene una clase real por ahora), `credentials` (nullable, cast `encrypted:array`, nunca en texto plano), `status` (enum `EmailProviderStatus`: activo/inactivo), timestamps, soft deletes.

**`email_accounts`**: `client_id` (FK clients, cascade), `email_provider_id` (FK email_providers, `restrictOnDelete` — no se puede borrar un proveedor con cuentas activas), `email_address` (único), `status` (enum `EmailAccountStatus`: activa/suspendida), `provisioned_at`, timestamps, soft deletes. La contraseña de la cuenta **nunca se persiste**: solo vive como propiedad transitoria de Livewire mientras se llama al driver, igual que en el formulario de creación.

### Archivos clave creados

- `app/Services/EmailProvisioning/Contracts/EmailProviderDriver.php` — interfaz con `createMailbox`, `deleteMailbox`, `changePassword`, `listMailboxes`, `getConnectionSettings`, tal como se planeó desde el inicio.
- `app/Services/EmailProvisioning/Drivers/NullEmailProviderDriver.php` — implementación simulada: los métodos de escritura son no-op, `listMailboxes` lee las cuentas ya guardadas localmente (no hay API remota que consultar) y `getConnectionSettings` devuelve host/puerto de ejemplo, dejando claro en el docblock que es un sustituto temporal.
- `app/Models/EmailProvider.php` — método `driver()` que resuelve la implementación concreta con un `match` sobre el enum; lanza una excepción clara si se intenta usar un driver todavía no implementado (mxroute/cpanel/hostinger).
- `app/Actions/EmailAccounts/{ProvisionEmailAccount,DeleteEmailAccount,ChangeEmailAccountPassword}.php` — cada una resuelve el driver del proveedor y llama al método correspondiente del contrato antes de tocar la base de datos local.
- `app/Policies/EmailProviderPolicy.php` — solo admin (las credenciales de proveedor son más sensibles que el resto del CRM, así que ni siquiera `staff` administra proveedores). `email_accounts` no tiene policy propia: se gestiona autorizando contra `update` del `Client` padre, igual que `ClientNote`.
- `routes/crm.php`: grupo nuevo `role:admin` con `email-providers.index` (`/proveedores-correo`).
- `resources/views/pages/email-providers/⚡index.blade.php` — CRUD de proveedores (el selector de driver en el formulario solo ofrece `EmailProviderDriverType::implemented()`, para no dejar elegir un driver que todavía no existe).
- `resources/views/pages/clients/⚡show.blade.php` — nueva tarjeta "Cuentas de correo" (visible solo para admin/staff, mismo guard que "Cuentas de correo" y "Agencias colaboradoras"): alta de cuenta (proveedor + correo + contraseña), badge de estatus, botón para cambiar contraseña (modal) y botón para eliminar (con `wire:confirm`).
- Sidebar: nuevo grupo "Correo" con el ítem "Proveedores", visible solo para admin.
- Tests: `tests/Feature/EmailProviders/EmailProviderManagementTest.php` (5 tests: acceso por rol, CRUD) + `tests/Feature/EmailAccounts/EmailAccountManagementTest.php` (4 tests: alta/baja/cambio de contraseña desde el detalle de cliente, `collaborator` sin acceso).

### Seeders y factories

- `database/factories/{EmailProvider,EmailAccount}Factory.php`.
- `database/seeders/EmailProviderSeeder.php`: crea "MXroute (simulado)" y aprovisiona (vía la acción real, no inserción directa) una cuenta para cada uno de los primeros dos clientes ya sembrados, para demostrar el flujo de punta a punta sin credenciales reales. Registrado en `DatabaseSeeder` después de `AgencySeeder`.

### Pendiente para cerrar la fase por completo

- Conectar un driver real (MXroute primero, según lo confirmado) cuando el usuario tenga credenciales/documentación de su API.

## Fase 4b — Cuentas de correo propias en el portal ✅

Con `email_accounts` ya existente desde la Fase 5, se agregó la vista de solo lectura correspondiente en el portal.

### Archivos clave creados

- `routes/portal.php`: `portal.email-accounts.index` (`/portal/correo`), mismo grupo `role:client` que las rutas de proyectos.
- `resources/views/pages/portal/email-accounts/⚡index.blade.php` — lista las cuentas de correo del cliente autenticado (`EmailAccount::where('client_id', auth()->user()->client_id)`, mismo patrón que `portal.projects.index`, sin política propia). Por cada cuenta muestra su estatus y la configuración de conexión (`$emailAccount->provider->driver()->getConnectionSettings(...)`) — con el driver simulado de Fase 5 esto son valores de ejemplo, pero en cuanto haya un driver real la misma vista mostrará datos reales sin cambios.
- `resources/views/layouts/portal.blade.php` — se agregó un `flux:navbar` con dos ítems ("Proyectos" / "Correo") entre el logo y el menú de usuario, ya que con dos secciones el portal necesitaba alguna forma de navegar entre ellas (la decisión original de "sin nav interno" se refería a no reutilizar el sidebar completo del CRM interno, no a no tener ningún enlace).
- Tests: `tests/Feature/Portal/PortalEmailAccountsTest.php` (5 tests: acceso por rol, scope a cuentas propias, configuración de conexión visible, `client` sin `client_id` vinculado).

## Fase 6 — Dashboard interno ✅

### Alcance

Se reemplazó el placeholder del starter kit de Livewire en `/dashboard` (bloques `x-placeholder-pattern` sin datos) por un dashboard real, con contenido distinto según rol y respetando el mismo criterio de "sin acceso a datos financieros" ya aplicado en proyectos/agencias/correo para `collaborator`. No requirió modelo de datos nuevo: solo consultas de agregación sobre `charges`, `projects` y `clients` ya existentes.

- **Admin/Staff** ven: tarjetas KPI (cobros pendientes y vencidos con su suma, proyectos activos, prospectos abiertos), tabla "Próximos cobros" (vencimiento en los próximos 7 días) y "Actividad reciente" (últimas 8 `ClientNote` de cualquier cliente).
- **Collaborator** ve: tarjeta "Mis proyectos activos" y una tabla con sus proyectos asignados (vía `project_user`) — sin montos, cobros, ni actividad de clientes ajenos.
- **Client**: sin cambios, la misma ruta `dashboard` lo sigue redirigiendo a `/portal` (comportamiento de la Fase 4a).

### Archivos clave creados/modificados

- `resources/views/pages/dashboard/⚡index.blade.php` — componente Livewire de clase única (mismo patrón `new class extends Component` que el resto de `pages::`), con `#[Computed]` por bloque de datos (`pendingCharges`, `overdueCharges`, `activeProjectsCount`, `openProspectsCount`, `upcomingCharges`, `recentActivity`, `myAssignedProjects`); `activeProjectsCount` se filtra a los proyectos asignados cuando el usuario es `collaborator` (mismo patrón `whereHas('users', ...)` que `projects/⚡index.blade.php`). La redirección de `client` a `/portal` ahora vive en `mount()` (`$this->redirect(...)`) en vez del closure de ruta.
- `routes/web.php` — la ruta `dashboard` pasó de un closure con `view('dashboard')` a `Route::livewire('dashboard', 'pages::dashboard.index')`.
- `resources/views/dashboard.blade.php` — eliminado (reemplazado por el componente anterior).
- Sin policy nueva: el dashboard no expone un modelo propio: cada `#[Computed]` filtra según `auth()->user()->role`, igual que ya se hace en la vista de proyecto para ocultar montos a `collaborator`.
- Tests: `tests/Feature/DashboardTest.php` ampliado (7 tests: acceso por rol —incluye el guest/redirect ya existente—, admin ve KPIs/próximos cobros/actividad reciente, staff ve el mismo dashboard que admin, collaborator no ve datos financieros y solo ve sus proyectos asignados, conteo de prospectos abiertos excluye ganados/perdidos).

### Verificación

`php artisan test --compact` → 101 tests (99 pasan, 2 se saltan), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, flujo verificado en `https://sites.test` con los 3 roles (ver nota de verificación arriba).

## Fase 7 — Dominios, tipos de proyecto y campañas de ads ✅

> Nace de dos problemas detectados por el dueño de la agencia: (a) los proyectos tienen circunstancias muy distintas según su naturaleza (web, mantenimiento, ads) y hoy son todos la misma tabla con nombre libre; (b) las cuentas de correo cuelgan directamente del cliente (`email_accounts.client_id`), pero un cliente puede tener varios dominios registrados, así que el correo quedaba sin un dueño claro.

### Diagnóstico

La entidad que falta no es "un tipo de proyecto de correo" sino **el dominio**. Un buzón no pertenece a un cliente, pertenece a `acme.com`; y un dominio tiene vida propia (registrador, expiración, renovación anual). Colgar los correos de un "proyecto de email" solo movía la ambigüedad un nivel: si el cliente tiene tres dominios, ese proyecto vuelve a tener el mismo problema.

Además faltaban dos clasificadores: `projects` no tenía tipo (así que había que recapturar a mano los servicios típicos en cada proyecto web) y `services` no tenía categoría (`name` es texto libre, así que "Hosting" y "hosting anual" son cosas distintas para la base y no se puede ligar la renovación de dominio *al dominio*).

### Decisiones confirmadas por el usuario en esta fase

- **El dominio pertenece al cliente** (`client_id` obligatorio); el proyecto es un vínculo opcional (`project_id` nullable), no el dueño. Así un dominio puede reasignarse entre proyectos o quedar sin proyecto sin perder los buzones.
- **El tipo de proyecto actúa como plantilla al crear**, no como esquema rígido: precarga los servicios típicos con su frecuencia y el usuario ajusta.
- **El correo se administra por dominio, no por cliente** — un mismo cliente puede tener `acme.com` con nosotros y `acme.mx` en Google Workspace.
- **El correo del dominio solo se activa si su proyecto tiene la bandera `includes_email`**, que el tipo de proyecto precarga (web → sí, email → sí, ads/mantenimiento → no) y es ajustable. Esto reconcilia "el proyecto web normalmente incluye hosting, ssl, mail, website" con "debe existir proyecto de email para que se active".
- **Se persisten contraseñas de buzones** (cifradas), revirtiendo la decisión de la Fase 5 de no persistirlas nunca. Motivo: la agencia administra correo en proveedores para los que no habrá driver, y sin guardarlas nadie puede recuperarlas. Se asume el riesgo residual de que un atacante con base de datos **y** `APP_KEY` obtendría todas.
- **Visibilidad de la contraseña**: en el portal del cliente, oculta por defecto y revelable con un clic; en el CRM interno, **solo admin** (mismo criterio que `EmailProviderPolicy`). Consecuencia operativa aceptada: el staff que da de alta un buzón manual conoce la contraseña al capturarla, pero no puede volver a consultarla después.
- **La importación de buzones existentes se construye en esta fase** contra el driver simulado, ya que `EmailProviderDriver::listMailboxes()` existe desde la Fase 5.
- **Las migraciones existentes se reescriben en su lugar** en vez de agregar migraciones de `add_x_to_y`: no hay producción ni datos que preservar (base actual: 1 cliente, 0 correos/proyectos/servicios/cobros). Se resiembra con `php artisan migrate:fresh --seed` y no se escribe lógica de backfill.

### 7.1 Nueva entidad `Domain`

**`domains`**: `client_id` (FK clients, cascade, **obligatorio** — dueño), `project_id` (FK projects, nullOnDelete, **nullable** — vínculo), `name`, `management` (enum `DomainManagement`: `managed` = lo registramos y cobramos / `tracked` = solo damos seguimiento), `registrar` (nullable), `registered_at` (nullable), `expires_at` (nullable), `auto_renew` (bool), `email_management` (enum `DomainEmailManagement`: `managed` / `not_managed`), `email_notes` (text nullable — constancia de dónde vive el correo cuando no lo administramos), `status` (enum `DomainStatus`), timestamps, softDeletes. Índice único **`(client_id, name)`**, no global: dos clientes distintos pueden tener buzones en un mismo dominio público.

- `Client::domains()`, `Project::domains()` (`hasMany`); `Domain::client()/project()/emailAccounts()/services()`.
- `email_management` solo puede ponerse en `managed` si el dominio tiene `project_id` y ese proyecto tiene `includes_email = true`. Validación en el formulario y en una regla del modelo/acción.
- Los dominios `managed` con `expires_at` entran al comando `charges:process` existente como recordatorio de renovación, reusando el patrón `due_soon_notified_at` de `charges`.

### 7.2 Los correos se mueven del cliente al dominio

- `email_accounts`: se reemplaza `client_id` por `domain_id` (FK domains, cascade). Se agregan `password` (nullable, cast `encrypted` — nunca en texto plano) y `origin` (enum `EmailAccountOrigin`: `provisioned` = la creamos desde el CRM / `imported` = ya existía en el proveedor y se vinculó).
- El scoping del portal pasa a `whereHas('domain', fn ($q) => $q->where('client_id', ...))`.
- La tarjeta "Cuentas de correo" se mueve de `clients/⚡show.blade.php` al detalle del proyecto, anidada bajo cada dominio. El detalle de cliente conserva un resumen de solo lectura agrupado por dominio.
- `portal/email-accounts/⚡index.blade.php` agrupa por dominio y agrega el botón "mostrar contraseña" (oculta por defecto).
- Archivos afectados (14): `app/Models/{Client,EmailAccount,EmailProvider}.php`, `app/Actions/EmailAccounts/*` (3), `app/Services/EmailProvisioning/Drivers/NullEmailProviderDriver.php`, `resources/views/pages/clients/⚡show.blade.php`, `resources/views/pages/portal/email-accounts/⚡index.blade.php`, `resources/views/pages/email-providers/⚡index.blade.php`, `database/seeders/EmailProviderSeeder.php`, `database/factories/EmailAccountFactory.php`, `tests/Feature/Portal/PortalEmailAccountsTest.php`, `tests/Feature/EmailAccounts/EmailAccountManagementTest.php`.

### 7.3 Proveedor `manual` e importación de buzones

Nuevo caso `Manual` en `EmailProviderDriverType` con su `ManualEmailProviderDriver`: escrituras no-op (el alta la hace un humano en el panel del proveedor) pero el CRM registra la cuenta, guarda la contraseña y devuelve la configuración de conexión. `email_providers` gana `connection_settings` (json **sin cifrar**: host/puertos IMAP-SMTP no son secretos y el portal los necesita), junto al `credentials` cifrado que ya tiene.

| Proveedor | Alta/baja/cambio de pass | Contraseña en BD | Config de conexión |
|---|---|---|---|
| MXroute / cPanel (driver real, futuro) | vía API | no hace falta | del driver |
| `manual` (lo administra la agencia a mano) | no-op, solo registro | sí, cifrada | del proveedor |
| `null` (simulado, pruebas) | no-op | — | de ejemplo |

**Importación**: pantalla que llama a `listMailboxes()` del proveedor para un dominio, lista los buzones que existen en el servidor y deja marcar cuáles registrar en el CRM (`origin = imported`, `provisioned_at` vacío). Los no marcados simplemente no existen para el sistema. Se construye contra el driver simulado.

### 7.4 `ProjectType` como plantilla

- Enum `ProjectType`: `web`, `maintenance`, `ads`, `email`, `other`. Columnas `projects.type` y `projects.includes_email` (bool).
- Cada tipo declara una plantilla de servicios sugeridos (nombre + categoría + frecuencia) que el formulario de alta precarga y el usuario edita o quita antes de guardar:
  - **web**: Sitio web (`one_time`) + Hosting, SSL, Correo, Dominio (`annual`) · `includes_email = true`
  - **maintenance**: Mantenimiento (frecuencia a elegir) · `includes_email = false`
  - **ads**: Fee de gestión (`monthly`) + Inversión publicitaria (opcional) · `includes_email = false`
  - **email**: Correo (`annual`) · `includes_email = true`
- Acción `CreateProjectFromTemplate` que envuelve el `CreateServiceWithSchedule` existente, para no duplicar la lógica de cuotas/`next_charge_date`.
- Filtro por tipo en el listado de proyectos y en el dashboard.

### 7.5 `ServiceCategory` y frecuencias faltantes

- Enum `ServiceCategory`: `website`, `hosting`, `ssl`, `domain`, `email`, `maintenance`, `ads_management`, `ads_budget`, `other`. Columna `services.category`.
- `services.domain_id` (nullable): liga la renovación de dominio o el servicio de correo al dominio concreto, para que el cobro anual diga de qué dominio es.
- `ServiceBillingFrequency` gana `Quarterly` (trimestral) y `Semiannual` (semestral) — el dueño mencionó explícitamente mantenimiento trimestral. `GenerateScheduledCharges` ya resuelve con un `match` sobre el enum; solo se añaden los casos.

### 7.6 Campañas de ads

Como el presupuesto a veces pasa por la agencia y a veces lo paga el cliente directo a la plataforma, y un proyecto puede tener Meta y Google a la vez, va en tabla propia.

**`ad_campaigns`**: `project_id` (FK, cascade), `platform` (enum `AdPlatform`: meta/google/tiktok/linkedin/other), `ad_account_id` (nullable), `name`, `objective` (nullable), `monthly_budget` + `currency`, `budget_billing` (enum `AdBudgetBilling`: `pass_through` = se lo cobramos nosotros / `client_direct` = paga la plataforma directo), `starts_on`, `ends_on` (nullable), `status`, timestamps.

- Con `pass_through` se genera un `Service` de categoría `ads_budget` ligado a la campaña, separado del fee de gestión (`ads_management`), para que el cobro distinga honorarios de inversión publicitaria.
- Con `client_direct` el presupuesto queda solo como referencia y no genera cobros.
- Tarjeta "Campañas" en el detalle de proyecto, oculta a `collaborator` (mismo guard que Cobros/Agencias).

### 7.7 Orden de trabajo

1. Migraciones (reescritas en su lugar), enums y modelos de 7.1 y 7.5.
2. Mover la UI de correos de cliente → dominio, ajustar el portal y actualizar los 14 archivos con sus tests.
3. Proveedor `manual`, `connection_settings`, contraseñas cifradas e importación de buzones (7.3).
4. `ProjectType`, `includes_email` y plantillas (7.4).
5. `ad_campaigns` y tarjeta de campañas (7.6).
6. Recordatorio de expiración de dominio en `charges:process` (7.1).

Verificación en cada paso: `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` (nivel 7), `php artisan test --compact`.

### Implementación (lo que realmente se construyó)

Los seis pasos del orden de trabajo están hechos. Lo que quedó distinto de lo planeado, y por qué:

**Correo y dominios**
- `EmailProviderDriver::listMailboxes()` cambió de firma a `listMailboxes(EmailProvider $provider, string $domain)`. Sin el dominio la pantalla de importación no podía preguntar "qué buzones existen en *este* dominio", que además es como listan los proveedores reales (cPanel y MXroute listan por dominio).
- Quién guarda la contraseña lo decide el **proveedor**, no la cuenta: `EmailProviderDriverType::storesPasswordLocally()` devuelve `true` solo para `manual`. Un driver con API puede resetear la contraseña cuando sea; uno manual no, y perderla ahí es perderla para siempre. Un test lee la columna cruda con `DB::table()` para comprobar que nunca queda en texto plano.
- `NullEmailProviderDriver::listMailboxes()` devuelve, además de los buzones ya registrados, tres locales convencionales (`info@`, `ventas@`, `soporte@`) para que la pantalla de importación tenga algo que ofrecer mientras no hay API real. Está documentado en el propio driver como sustituto de la llamada remota.
- La tarjeta de dominios y buzones vive en `app/Livewire/ProjectDomains.php` (componente de clase, mismo patrón que `NotificationsBell`) y no dentro de `pages::projects.show`, que ya carga servicios, equipo, cobros y agencias, y porque los formularios de buzón traen tres modales propios.

**Tipos de proyecto y servicios**
- Al agregar `Quarterly` y `Semiannual` se movió la lógica de recurrencia al propio enum (`isRecurring()`, `recurring()`, `advanceFrom()`). Antes vivía repartida entre un `whereIn` en `GenerateScheduledCharges` y un ternario mensual-o-anual que habría dado silenciosamente mal la fecha para las frecuencias nuevas.
- El formulario de servicio ganó **categoría** y **dominio**. Sin eso `services.category` y `services.domain_id` eran inalcanzables desde la interfaz y todo servicio manual habría quedado en `other`. El selector de dominio solo aparece para categorías que tienen sentido por dominio (`ServiceCategory::belongsToDomain()`) y está acotado a los dominios de ese proyecto.
- `projects.type` y `services.category` arrancan en `other` también como default de propiedad del componente, no solo de columna, para que el formulario sea válido aunque no se pase por el modal de alta.
- Se corrigió un bug previo: el toast al **crear** un proyecto decía "Proyecto actualizado", porque la variable ya estaba asignada cuando se evaluaba el ternario.

**Campañas de ads**
- Se agregó `services.ad_campaign_id` para ligar el servicio de inversión a su campaña.
- El servicio de inversión publicitaria es **opt-in**: al crear una campaña `pass_through` aparece una casilla "Crear servicio mensual de inversión publicitaria", marcada por defecto. Se dejó como elección y no como automatismo porque algunas campañas se facturan fuera del CRM, y un servicio que aparece solo significa cobros que aparecen solos. La tarjeta avisa en ámbar cuando una campaña `pass_through` se quedó sin servicio, que es el caso fácil de olvidar.
- `AdBudgetBilling::isBilledByUs()` concentra la distinción: con `client_direct` el presupuesto es solo una cifra de referencia y nunca toca `charges` — si se colara, inflaría todos los KPIs del dashboard.
- Vive en `app/Livewire/ProjectCampaigns.php`, mismo criterio que `ProjectDomains`.

**Recordatorios de dominio**
- La resolución de destinatarios se extrajo a `app/Actions/Notifications/NotifyProjectTeam.php` y ahora la comparten los recordatorios de cobro y los de dominio. Acepta un proyecto nulo: un dominio sin proyecto solo alerta a los admins.
- `Domain::booted()` limpia `expiry_notified_at` cuando cambia `expires_at`. Sin eso, renovar un dominio dejaba el aviso apagado para siempre y solo se recibía una alerta en toda la vida del registro.
- Solo se avisa de dominios `managed`: los `tracked` los renueva quien sea su dueño. **Pendiente de decidir**: si conviene avisar también de los `tracked`, ya que un dominio ajeno que expira igual tumba el sitio del cliente.
- El comando conserva el nombre `charges:process` (cambiarlo rompería el `Schedule` ya registrado) pero su descripción ahora dice que también envía los recordatorios de expiración de dominios.

### Verificación

`php artisan test --compact` → 137 tests (135 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` y `php artisan charges:process` corridos sin errores, y flujo revisado en `https://sites.test` con la sesión de admin: tarjeta de dominios con buzones anidados, resumen de solo lectura en el detalle de cliente, la regla de `includes_email` deshabilitando el correo en un proyecto que no lo incluye, importación de buzones de punta a punta, formulario de proveedor con datos de conexión, plantilla de servicios al elegir tipo de proyecto, y las dos ramas de facturación de presupuesto en campañas.

### Sigue pendiente

- Conectar el driver real de MXroute cuando haya credenciales (viene de la Fase 5; toda la aplicación ya está construida contra la interfaz).

## Fase 8 — Contactos como entidad propia ✅

> Nace de una pregunta del dueño de la agencia: "los clientes pueden tener varias empresas, ¿qué recomiendas?".

### Diagnóstico

La palabra "cliente" significaba dos cosas distintas. Para el dueño, el cliente es **la persona**: Juan Pérez. En la base de datos, `clients` es **la empresa**: es lo que tiene proyectos, dominios, correos y cobros. Como los datos de la persona (`contact_name`, `email`, `phone`) vivían dentro de `clients`, un dueño con tres empresas quedaba escrito tres veces: la bitácora partida, cambiar un teléfono eran tres ediciones, y ninguna ficha revelaba que las otras dos también eran suyas.

Se descartó la modelación "de libro" —convertir `clients` en la persona y crear `companies` debajo— porque obligaba a reparentar proyectos, dominios, correos, portal, políticas y casi toda la suite, para llegar al mismo resultado visible. También se descartó un agrupador tipo `account`: con el contacto bien modelado, "las empresas de Juan" ya es una consulta a la relación, sin tabla extra.

### Decisiones confirmadas por el usuario

- **`clients` sigue siendo la empresa** y el ancla de proyectos, dominios y cobros. Nada aguas abajo se movió.
- Se asume que **se factura por empresa**, no consolidado a la persona. Si eso cambiara, habría que revisar dónde vive el ancla de cobros.

### Modelo de datos (implementado)

**`contacts`**: `name`, `email` (nullable, único), `phone`, `notes`, timestamps, soft deletes. Una fila por persona.

**`client_contact`** (pivot, clave primaria compuesta): `role` (cargo), `is_primary`. Una empresa puede tener varios contactos y una persona puede estar en varias empresas.

**`clients`**: pierde `contact_name`, `email` y `phone`.

**`users`**: `client_id` pasa a `contact_id`. El acceso de portal cuelga de la persona, así que un dueño con tres empresas entra una sola vez y las ve todas. Esto además deshizo el ciclo `users ↔ clients` que obligaba a agregar una llave foránea fuera de su `create`.

### Archivos clave

- `app/Models/Contact.php`, `Client::contacts()` / `primaryContact()`, `User::contact()` / `clients()`.
- `app/Actions/Clients/LinkContactToClient.php` — resuelve los datos capturados contra `contacts`: si la persona ya existe (por correo, o por nombre si no hay correo) la reutiliza en vez de duplicarla. Un valor capturado siempre pisa al guardado, porque editar el contacto desde la ficha del cliente tiene que guardar; uno vacío no pisa nada, para que ligar a alguien existente sin repetir su teléfono no se lo borre.
- `app/Policies/ContactPolicy.php` — mismo criterio que `ClientPolicy`.
- `resources/views/pages/contacts/⚡show.blade.php` — la ficha de la persona: sus empresas con cargo, número de proyectos y dominios; la vista "todo lo de Juan" que no existía cuando el contacto vivía duplicado dentro de cada cliente. **No hay directorio de contactos**: a una persona se llega entrando a su empresa y pulsando su nombre en la tarjeta "Contactos". Un listado global de gente resultó no usarse, porque la consulta siempre empieza por el cliente. Registrar y editar personas ocurre, respectivamente, en el detalle de la empresa y en esta ficha; la ruta es `clientes/contactos/{contact}`, colgada de clientes para que la URL diga lo mismo que la navegación.
- `resources/views/pages/clients/⚡index.blade.php` — el formulario conserva los mismos tres campos de contacto, así que la captura no cambió; lo que cambió es que ahora resuelven contra `contacts` en vez de duplicar.
- `resources/views/pages/clients/⚡show.blade.php` — tarjeta "Contactos" con cargo, marca de principal, y acciones para cambiar el principal o desvincular. Desvincular conserva a la persona y sus demás empresas.
- Portal: `portal/projects` y `portal/email-accounts` se acotan a las empresas del contacto; la columna "Empresa" aparece solo cuando hay más de una.

### Nota sobre el alcance del commit

El cambio no se pudo partir en commits independientes que quedaran verdes por separado: quitar columnas de `clients` y crear `contacts` es atómico, y el formulario de cliente, el portal y las pruebas dejan de funcionar en el instante intermedio. Se commiteó como un solo cambio a propósito.

### Verificación

`php artisan test --compact` → 154 tests (152 pasan, 2 se saltan), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, y revisado en `https://sites.test`: el directorio de contactos, la ficha de Juan Pérez con sus tres empresas, y la tarjeta de contactos en el detalle de cliente. El seeder incluye a propósito el caso que motivó la fase: una persona dueña de tres empresas, escrita una sola vez.

## Modelo objetivo y hoja de ruta (Fases 9+)

> Escrito tras revisar los tres archivos que el dueño de la agencia usa hoy en paralelo al sistema: un CSV de líneas cobrables del año, un libro de Excel del VPS (hojas `Cuentas`, `Emails`, `Sitios`) y su seguimiento de renovaciones. El objetivo declarado es centralizar los tres, ganar avisos automáticos de caducidad, y eventualmente ofrecer el sistema a las agencias con las que trabaja.

### El problema conceptual que resuelve

Al listar lo que quería, el dueño enumeró "proyectos", "tareas cobrables" y "servicios" como tres cosas distintas y preguntó cuándo usar cada una. No hay respuesta a esa pregunta porque la división está mal trazada: **"Quitar sección Evaled, $500" y "Ecommerce Refrigeron, $39,500" no son de naturaleza distinta**, se diferencian en tamaño. Ambos se cobran una vez, tienen principio y fin.

Las preguntas que sí separan son tres:

| Pregunta | Qué es |
|---|---|
| ¿El cobro **se repite** en un ciclo? | Servicio recurrente |
| ¿Se cobra **una vez**? | Trabajo |
| ¿Es algo que el cliente **tiene** y nosotros administramos? | Activo |

Un proyecto no es una tercera categoría: es un **agrupador opcional** para trabajos grandes que necesitan desglose. Y una campaña de ads, que era el caso que no encajaba, se parte en los tres: la campaña es un **activo** (plataforma, cuenta publicitaria, objetivo, presupuesto), su fee mensual es un **servicio recurrente**, su montaje inicial fue un **trabajo**.

### Los activos son una sola familia

Dominio, sitio, buzón, licencia y campaña comparten forma: tienen datos y accesos, pertenecen al cliente, caducan o corren, y **generan los cobros recurrentes**. Tratarlos como una familia da gratis el aviso de caducidad para todos, no solo para dominios.

El servicio recurrente deja de confundirse con el activo: es **el cobro que ese activo genera**. Se cobran $4,000 anuales (un servicio) por hosting + SSL + dominio (tres activos).

### Dónde viven los accesos, y por qué no en el proyecto

El dueño quería que el "proyecto web" contuviera todos los datos, accesos y licencias. Se descartó por dos razones tomadas de sus propios datos:

- **Los proyectos terminan y los accesos no.** Sus dominios están dados de alta desde 2015; sus proyectos duran meses. Enterrar un cPanel en un proyecto cerrado esconde justo el dato que se necesita un martes cualquiera.
- **Hay clientes sin ningún proyecto.** Momat, Gee y Previmed solo tienen "Renovación Anual". Exigir proyecto obligaría a inventar proyectos falsos para guardar una contraseña.

El contenedor que buscaba **ya existe y es la ficha del cliente**. El proyecto es el trabajo que se hace, no el expediente.

Reparto final: **accesos técnicos** (cPanel, base de datos, FTP, CMS) cuelgan del dominio, porque son del sitio y el sitio vive en el dominio. **Licencias y suscripciones** cuelgan del cliente con dominio opcional, porque Brevo es del cliente y una licencia de tema es de un sitio.

Se descartó introducir un `Sitio` como entidad intermedia entre dominio y proyecto: sería lo correcto en teoría —un sitio puede tener varios dominios apuntándole— pero en los datos reales hay 15 dominios y 14 sitios, prácticamente uno a uno, y los subdominios (`app.solyva.mx`) entran como dominio propio porque el índice único ya es `(client_id, name)`. Se agregará esa capa el día que exista el caso.

### La adaptación: qué se afloja y qué se agrega

El modelo actual no se tira. Se **afloja en dos puntos** y se le agregan activos:

1. **El proyecto obligatorio.** Hoy `services.project_id` es requerido, así que una línea de $500 exige construir proyecto y servicio. Pasa a: `services.client_id` requerido, `project_id` nullable. Una línea suelta cuelga del cliente; una grande se agrupa en un proyecto.
2. **El cobro binario.** Hoy un `Charge` es pendiente/pagado/vencido. Los pagos parciales están por todas partes en los datos reales —$12,914 de $24,000, dos transferencias en fechas distintas— y "Restante" es la columna que más se mira. El cobro pasa a tener **abonos**, y su estatus se deriva.

Lo demás es aditivo: credenciales, licencias, subtareas, y el ciclo de renovación.

### Fase 9 — Activos: credenciales y licencias ✅

Mata el libro del VPS. **Implementada.**

- **`domain_credentials`**: `domain_id`, `kind` (panel/base de datos/FTP/CMS/otro), `label`, `url`, `username`, `password` (cifrada), `notes`. Una fila por acceso en vez de columnas fijas, porque no todos los sitios tienen WordPress ni FTP y alguno tendrá dos bases de datos.
- **`licenses`**: `client_id` (requerido), `domain_id` (nullable), `name`, `vendor`, `cost`, `renewal_date`, `status`, credenciales opcionales, `notes`.
- **`domains`**: se agregan `hosting_plan` (texto libre — puede ser VPS o compartido), `site_url` y `vps_added_at`.
- **Plantillas de conexión**: `email_providers.connection_settings` debe admitir `mail.{dominio}`, porque la configuración real se deriva del dominio y hoy obligaría a capturar lo mismo 15 veces.
- **Importación** del libro `VPS Controlmas.xlsx` (fechas en serial de Excel).

**Nota de seguridad, no opcional.** Ese libro es una bóveda de credenciales en texto plano: cPanel, base de datos, FTP y WordPress de 14 sitios. Centralizarlo mejora las cosas —quedan cifradas y con control de acceso— pero **sube la apuesta del CRM**: pasa de herramienta de gestión a custodio del acceso a la infraestructura de los clientes. De ahí que estas credenciales sean **solo admin** (mismo criterio que los proveedores de correo) y **nunca visibles en el portal**: el buzón sí es del cliente, el cPanel es infraestructura. Las contraseñas que ya viajaron en ese archivo deben rotarse tras migrar.

**Lo que se construyó**

- `domain_credentials` y `licenses`, ambas con la contraseña cifrada; `domains` ganó `site_url`, `hosting_plan` y `hosted_since`.
- `ProjectDomains` se generalizó a **`DomainsPanel`**: recibe siempre el cliente y opcionalmente un proyecto. Sin proyecto lista todos los dominios del cliente, que era la única forma de administrar los de quien solo tiene hosting — y son la mayoría de los importados. El detalle de cliente pasó de un resumen de solo lectura a este panel completo.
- `ClientLicenses` en la ficha del cliente. Las credenciales de licencia siguen el mismo criterio que los accesos: solo admin, y un campo de contraseña vacío al editar conserva la guardada en vez de borrarla.
- `getConnectionSettings()` recibe el dominio y sustituye `{dominio}`, porque la configuración real se deriva de él (`mail.acme.com`) y guardarla literal obligaba a capturar lo mismo una vez por dominio.
- `App\Services\Import\XlsxReader`: lector de `.xlsx` sobre `ZipArchive` y `SimpleXML`, sin dependencias nuevas para algo que se usa una vez.
- `php artisan import:hosting {archivo} [--provider=] [--dry-run]`, idempotente. La corrida en seco recorre el camino real dentro de una transacción y la deshace al final; simularla con ramas aparte daba conteos falsos, porque sin escribir nada la deduplicación nunca ocurría y cada fila parecía un cliente nuevo.

**Resultado de la importación real**: 19 clientes, 19 dominios, 59 buzones, 23 accesos y 9 contactos. Los buzones van por defecto a un proveedor **manual**, porque traen contraseña y no hay API detrás; con un proveedor de API el importador las descarta y lo advierte.

**Dos cosas que la importación reveló**

- El libro **no trae el nombre de la empresa**, así que se deriva del dominio (`momat.com.mx` → "Momat"). Es una conjetura deliberada: es más rápido renombrar unos cuantos clientes después que capturar diecinueve a mano antes. Los nombres derivados tampoco coinciden con los del CSV de cobrables ("Geeaguasresiduales" contra "Gee"), así que al importar ese CSV habrá que unificar.
- **Ninguno de los 19 dominios traía fecha de renovación**, aunque 14 sí tenían plan y 11 fecha de alta en el VPS. Eso explica por qué el archivo se revisaba a mano: no había dato del cual disparar un aviso. Hasta que esas fechas se capturen, el tablero de la Fase 12 no tendrá nada que avisar.

**Una regla de la Fase 7 que los datos reales tumbaron**

Se había definido que administrar el correo de un dominio exigía además un proyecto con `includes_email`. Tenía sentido cuando se asumía que todo cliente tenía proyecto. Al importar el libro real quedó claro que no: **once de los trece dominios con correo no tienen proyecto**, y la regla dejaba sus 59 buzones invisibles en la interfaz.

Es el mismo argumento que ya había decidido dónde viven los accesos. Administrar el correo pasó a ser propiedad del dominio y de nadie más; el `includes_email` del proyecto dejó de ser candado y quedó como el valor que se **propone** al dar de alta un dominio desde un proyecto.

### Navegación: los proyectos dejan de ser el centro

El dueño observó que los proyectos no son ni lo más importante ni lo más frecuente —de unas 70 líneas cobrables al año, las que son proyectos de verdad son cinco o seis— y preguntó si debían perder su menú propio y vivir dentro del detalle del cliente.

Tiene razón en el diagnóstico, pero quitar el menú hoy deja dos huecos: **un colaborador no puede abrir un cliente** (`ClientPolicy` es solo admin y staff), así que el listado de proyectos es su única puerta de entrada al sistema; y se pierde la vista transversal ("qué proyectos están activos", "en qué anda cada colaborador"), que no se contesta entrando cliente por cliente.

Resolución en dos tiempos:

1. **Hecho**: el detalle de cliente tiene ahora una tarjeta "Proyectos" con sus proyectos, tipo, conteo de servicios y dominios, y estatus. Antes había que ir al listado y filtrar para ver los de un cliente. El alta sigue en el listado, para no duplicar el formulario con su maquinaria de plantillas de servicios.
2. **En la Fase 11**: el menú "Proyectos" se reemplaza por uno de **"Trabajos y cobros"**, que lista todo lo cobrable con filtros por tipo, estatus, cliente y agencia, y donde el proyecto es una columna más. Así el menú desaparece sustituido por algo más útil, en vez de recortado dejando huecos. Al colaborador se le resuelve la entrada convirtiendo su dashboard en su lista completa de proyectos asignados, que ya está a medio camino.

El problema no es que Proyectos tenga menú: es que es el menú equivocado.

### Fase 10 — Abonos, cobros editables y agencias ✅

Desbloquea mover el CSV de cobrables. **Implementada.**

- **`charge_payments`**: `charge_id`, `amount`, `paid_on`, `method`, `account`, `reference` (comprobante), `invoice_reference` (folio). El estatus del cobro se deriva: pendiente / parcial / pagado / vencido, y aparece el restante.
- **Cobros editables**: monto, fecha y concepto. Hoy solo se puede marcar pagado, y los montos cambian entre periodos (B2B pasó de $5,500 a $9,500; CMCP de $3,000 a $5,000 al subir de 12 a 20 horas).
- **Agencias reorientadas.** `Agency` está mal modelado: se construyó como "agencia colaboradora con facturación bidireccional" y en realidad es **de dónde viene el trabajo y quién paga**. AgenciaEfe5 es una agencia para la que trabaja como freelancer y cuyos clientes atiende (le factura a la agencia); IECA es una empresa cuyos temas internos atiende (cliente y pagador son la misma entidad); ControlMas es su propio negocio (le factura al cliente final). Se agrega a la agencia **a quién se le factura** (agencia o cliente final), se quita la bidireccionalidad —nadie le factura a él— y se agregan filtros y reportes por agencia, que es la primera columna de su hoja.

**Lo que se construyó**

- **`charge_payments`** con `amount`, `paid_on`, `method`, `account`, `reference` e `invoice_reference`. `Charge::syncStatusFromPayments()` deriva el estatus y la fecha de pago de los abonos; `paidAmount()` y `remainingAmount()` dan las dos columnas que el dueño mira.
- **`ChargeStatus` gana `Parcial`**, y con él un `color()` compartido para que el semáforo del cobro sea el mismo en el panel, el dashboard y el portal.
- **El estatus nunca se escribe a mano.** "Marcar pagado" dejó de tocar el campo: ahora registra el restante como un abono de hoy. Si no, un cobro podía verse pagado con el restante completo al lado, contradiciéndose solo.
- **Un cobro parcial vencido se muestra vencido**, no parcial: lo que falta sigue debiéndose y esa es la lista que hay que perseguir. El desglose "abonado X · restan Y" queda a la vista para no perder el matiz. `charges:process` y los recordatorios ahora incluyen los parciales.
- **Cobros editables**: concepto (que cae al nombre del servicio si se deja vacío), monto y vencimiento, con el estatus recalculado al guardar —bajar el monto por debajo de lo abonado deja el cobro pagado.
- **Un servicio ya no se puede borrar si alguno de sus cobros tiene abonos** (antes era "si tiene cobros pagados"): basta un abono parcial para que exista constancia de pago que no debe desaparecer.
- **Agencias**: `billing_target` (a la agencia / al cliente final) sustituye a la bidireccionalidad, que se eliminó del pivote `agency_project`. El listado filtra por ese campo y reporta, en una sola consulta por página, clientes, proyectos activos, cobrado y por cobrar de cada agencia. El listado de proyectos gana filtro por agencia.
- **El dashboard suma saldos, no montos**: "por cobrar" descuenta los abonos, que era la única forma de que el KPI no mintiera en cuanto hubiera un pago parcial.

**Lo que queda para la Fase 11**: la vista transversal de "Trabajos y cobros" que sustituye al menú de Proyectos. El filtro por agencia en el listado de proyectos es el puente mientras tanto.

### Fase 11 — Líneas sueltas, subtareas y campañas al cliente ✅

Mata el CSV de cobrables. **Implementada.**

- **`services.project_id` nullable** y `client_id` requerido.
- **Captura rápida**: un renglón siempre visible en la ficha del cliente —fecha, concepto, monto— sin modal, con fecha en hoy. Si no se captura tan rápido como una fila de Excel, se vuelve al Excel.
- **`service_items`** (subtareas): descripción, fecha, hecho/no hecho. Cubre las tres visitas del mantenimiento cuatrimestral —**confirmado: un cobro de $1,000 al año que cubre tres visitas, no $1,000 por visita**— y también la lista numerada de cambios que hoy escribe dentro del concepto de "Mejora continua".
- **Frecuencia quincenal** (IECA cobra dos veces al mes).
- **`ad_campaigns`** pasa de colgar del proyecto a colgar del cliente, con proyecto opcional: es un activo, no trabajo.

**Lo que se construyó**

- **`services.client_id` requerido y `project_id` nullable**, con lo mismo en `ad_campaigns`. `CreateServiceWithSchedule` recibe ahora el cliente y, opcionalmente, el proyecto.
- **Captura rápida** en la ficha del cliente: un renglón siempre visible con fecha (hoy), concepto, monto y frecuencia, sin modal. La frecuencia se dejó en el renglón —no solo pago único— porque las líneas sueltas reales del dueño son sobre todo renovaciones anuales, y esconderlas tras el formulario largo habría dejado el caso más común fuera de la vía rápida.
- **`service_items`**: descripción, fecha opcional y hecho/no hecho, con un contador "2/3" en la lista. Marcarlas no cobra de más: el monto es del servicio, y ese era justo el malentendido que había que evitar.
- **Frecuencia quincenal** anclada al día de inicio —el 3 y el 18—, en vez de sumar quince días y correrse de mes en mes.
- **Tres paneles reutilizables** en vez de dos pantallas que se copiaban: `ServicesPanel`, `ChargesPanel` y `CampaignsPanel` reciben el cliente y opcionalmente el proyecto, igual que `DomainsPanel` desde la Fase 9. El detalle de proyecto pasó de casi 600 líneas a poco más de 250, y la ficha del cliente ganó, sin código nuevo, su estado de cuenta completo: los cobros de sus líneas sueltas y los de sus proyectos en una sola tabla.
- **"Trabajos y cobros"** (`/trabajos`): todo lo cobrable con filtros por cliente, agencia, categoría, frecuencia y estatus, con el proyecto como una columna más y los totales de lo filtrado —no solo de la página— arriba. El menú de Proyectos se conserva debajo: quitarlo hoy dejaría sin puerta de entrada al alta de proyectos, que sigue viviendo en su listado.
- **El dashboard del colaborador es su lista completa** de proyectos asignados, activos o no, con los activos primero. Es su única entrada al sistema, porque no tiene menú de proyectos ni acceso a la ficha del cliente.

### Fase 12 — Renovaciones

Mata el Excel de renovaciones y entrega el aviso automático que motivó todo esto.

- **Tablero unificado de caducidades**: dominios, licencias y servicios anuales en una sola vista, "qué caduca en los próximos N días".
- **Ciclo explícito**: por avisar → avisado → renovó (genera la línea cobrable) → no renovó (baja). Hoy no hay dónde registrar "ya le avisé, estoy esperando".
- **Aviso al cliente**, no solo interno. **Con enlace al portal, nunca con las contraseñas en el cuerpo**: un correo se queda para siempre en la bandeja, se reenvía y se filtra. La pantalla donde el cliente ve sus datos y revela su contraseña con un clic ya existe.

### Fase 13 — Cotizaciones

Su CSV tiene filas en estatus *Pendiente* sin costo, con el precio escrito en Notas ("Mexico Juega — Costo $5500"): trabajo cotizado y no aceptado. Necesita un estado propio antes de que exista cobro.

### Fase 14 — Contratos

Con activos, servicios, montos, vigencias y entregas ya en el sistema, el contrato es un documento generable. No antes: sin las fases anteriores no hay de dónde sacarlo.

### Fuera de alcance por ahora

- **Costos internos y margen.** Cobra $4,000 por una renovación que le cuesta algo en el registrador. Aplazado por decisión explícita del dueño.
- **Registro de tiempo.** Las horas aparecen en sus conceptos ("-12 horas", "14 horas") pero como **tamaño del trato**, no como tiempo medido. No hay que construir cronómetros.
- **Multi-agencia (multi-tenencia).** El objetivo declarado es ofrecer el sistema a las agencias con las que trabaja. Construirlo ahora, sin una segunda agencia real usándolo, costaría complejidad en cada pantalla a cambio de nada. Lo correcto es no cerrarse la puerta: si el modelo se mantiene limpio —todo colgando del cliente, sin atajos— la migración es agregar una columna de organización a las tablas raíz y un filtro global, que es mecánico. Lo que sí encarece esa migración es acumular consultas que asuman "todos los clientes son míos", y de eso conviene cuidarse desde ahora sin costo.

  Advertencia para cuando llegue: AgenciaEfe5 existirá **dos veces** —como contraparte de facturación y como organización que entra a usar el sistema— y sus clientes finales, que hoy registra él, pasarían a ser de ellos. Es la razón de no sobreinvertir todavía en el modelo de agencias.

## Verificación (repetir en cada fase nueva)

- `vendor/bin/pint --format agent <rutas tocadas>` (pasar rutas explícitas; también funciona `--dirty` ya que el repo usa git).
- `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` (nivel 7; el límite de memoria por defecto de 128M no alcanza).
- `php artisan test --compact` (suite completa) — o el archivo específico de la fase durante el desarrollo.
- Verificar en navegador (`https://sites.test`) el flujo completo de la fase.
