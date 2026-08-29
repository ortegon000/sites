# CRM interno para agencia — Plan de implementación

> Copia de trabajo dentro del repo del plan original (`~/.claude/plans/linked-fluttering-shamir.md`), para que cualquier sesión de Claude Code que abras en este proyecto pueda encontrarlo y continuar. Si retomas esto en una conversación nueva, dile a Claude: **"Continúa con el plan en CRM_PLAN.md"**.

## Estado actual

- ✅ **Fase 0 — Roles y fundamento de auth**: completa.
- ✅ **Fase 1 — CRM de clientes y prospectos**: completa.
- ⬜ **Fase 2 — Proyectos → Servicios → Cobros + recordatorios**: pendiente.
- ⬜ **Fase 3 — Agencias colaboradoras**: pendiente.
- ⬜ **Fase 4 — Portal de clientes**: pendiente.
- ⬜ **Fase 5 — Aprovisionamiento de correo**: pendiente.

Verificación al cierre de Fase 0+1: `php artisan test --compact` → 40 tests (38 pasan, 2 se saltan por el registro deshabilitado), `vendor/bin/phpstan analyse` nivel 7 limpio, `vendor/bin/pint` sin hallazgos, y flujo probado manualmente en `https://sites.test`.

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

---

## Roadmap de fases futuras

- **Fase 2 — Proyectos → Servicios → Cobros + recordatorios**: `projects`, `project_user` (pivot que habilita "colaborador solo ve sus proyectos asignados"), `services` (con `billing_frequency`: `one_time`/`monthly`/`annual`/`installment`), `service_installments`, `charges` (cobros con status `pendiente`/`pagado`/`vencido`), comando programado diario (`routes/console.php` vía `Schedule::command(...)->daily()`) que genera cobros, marca vencidos y dispara recordatorios; notificaciones `ChargeDueSoonNotification`/`ChargeOverdueNotification` (`mail` + `database`), y un componente Livewire de clase `NotificationsBell` embebido en el sidebar.
- **Fase 3 — Agencias colaboradoras**: tablas `agencies` + `agency_project` (pivot con `billing_direction`: `we_invoice_them`/`they_invoice_us`).
- **Fase 4 — Portal de clientes**: layout propio `resources/views/layouts/portal.blade.php` (sin nav interno), rutas bajo `role:client`, vistas de solo lectura de proyectos y cobros propios (4a), luego cuentas de correo propias (4b, depende de Fase 5).
- **Fase 5 — Aprovisionamiento de correo**: interfaz de driver (`app/Services/EmailProvisioning/Contracts/EmailProviderDriver.php`: `createMailbox`, `deleteMailbox`, `changePassword`, `listMailboxes`, `getConnectionSettings`), tablas `email_providers`/`email_accounts` (credenciales con cast `encrypted`, nunca password en texto plano), driver MXroute primero (proveedor principal), cPanel y Hostinger después (requieren credenciales/documentación real del usuario).

## Verificación (repetir en cada fase nueva)

- `vendor/bin/pint --format agent <rutas tocadas>` (este repo no es git, así que `--dirty` no funciona; pasar rutas explícitas).
- `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` (nivel 7; el límite de memoria por defecto de 128M no alcanza).
- `php artisan test --compact` (suite completa) — o el archivo específico de la fase durante el desarrollo.
- Verificar en navegador (`https://sites.test`) el flujo completo de la fase.
