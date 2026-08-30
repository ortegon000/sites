# CRM interno para agencia — Plan de implementación

> Copia de trabajo dentro del repo del plan original (`~/.claude/plans/linked-fluttering-shamir.md`), para que cualquier sesión de Claude Code que abras en este proyecto pueda encontrarlo y continuar. Si retomas esto en una conversación nueva, dile a Claude: **"Continúa con el plan en CRM_PLAN.md"**.

## Estado actual

- ✅ **Fase 0 — Roles y fundamento de auth**: completa.
- ✅ **Fase 1 — CRM de clientes y prospectos**: completa.
- ✅ **Fase 2 — Proyectos → Servicios → Cobros + recordatorios**: completa.
- ✅ **Fase 3 — Agencias colaboradoras**: completa.
- ⬜ **Fase 4 — Portal de clientes**: pendiente.
- ⬜ **Fase 5 — Aprovisionamiento de correo**: pendiente.

Verificación al cierre de Fase 0+1: `php artisan test --compact` → 40 tests (38 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, y flujo probado manualmente en `https://sites.test`.

Verificación al cierre de Fase 2: `php artisan test --compact` → 58 tests (56 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` y `php artisan charges:process` corridos manualmente contra `https://sites.test` sin errores.

Verificación al cierre de Fase 3: `php artisan test --compact` → 67 tests (65 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, `php artisan migrate:fresh --seed` corrido manualmente y flujo completo verificado en `https://sites.test` (alta de agencia, asociación a proyecto con cada `billing_direction`, confirmación de que `collaborator` no ve la tarjeta).

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

---

## Roadmap de fases futuras

- **Fase 4 — Portal de clientes**: layout propio `resources/views/layouts/portal.blade.php` (sin nav interno), rutas bajo `role:client`, vistas de solo lectura de proyectos y cobros propios (4a), luego cuentas de correo propias (4b, depende de Fase 5).
- **Fase 5 — Aprovisionamiento de correo**: interfaz de driver (`app/Services/EmailProvisioning/Contracts/EmailProviderDriver.php`: `createMailbox`, `deleteMailbox`, `changePassword`, `listMailboxes`, `getConnectionSettings`), tablas `email_providers`/`email_accounts` (credenciales con cast `encrypted`, nunca password en texto plano), driver MXroute primero (proveedor principal), cPanel y Hostinger después (requieren credenciales/documentación real del usuario).

## Verificación (repetir en cada fase nueva)

- `vendor/bin/pint --format agent <rutas tocadas>` (pasar rutas explícitas; también funciona `--dirty` ya que el repo usa git).
- `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` (nivel 7; el límite de memoria por defecto de 128M no alcanza).
- `php artisan test --compact` (suite completa) — o el archivo específico de la fase durante el desarrollo.
- Verificar en navegador (`https://sites.test`) el flujo completo de la fase.
