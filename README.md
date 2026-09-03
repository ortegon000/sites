# CRM de Agencia

Aplicación interna a medida para gestionar clientes y sus contactos, proyectos y trabajos, cobros con recordatorios, dominios con sus buzones y accesos, licencias, campañas de ads y agencias, con un portal de solo lectura para el cliente.

Construida sobre Laravel 13 con el stack de [`laravel/livewire-starter-kit`](https://github.com/laravel/livewire-starter-kit): Livewire 4 (componentes de página nativos), [Flux UI](https://fluxui.dev/), Fortify (login, 2FA, passkeys) y Tailwind CSS v4.

## Qué modela

Tres familias, con una regla clara para elegir entre ellas:

| Pregunta | Qué es | Dónde vive |
|---|---|---|
| ¿El cobro **se repite** en un ciclo? | Servicio recurrente | `Service` con frecuencia |
| ¿Se cobra **una vez**? | Trabajo | `Service` de pago único o a plazos |
| ¿Es algo que el cliente **tiene** y administramos? | Activo | `Domain`, `EmailAccount`, `DomainCredential`, `License`, `AdCampaign` |

El **cliente es la empresa** y el centro de todo: de él cuelgan contactos, dominios, licencias, proyectos y bitácora. El **contacto es la persona**, guardada una sola vez y ligada a todas sus empresas, así que un dueño de tres negocios se escribe una vez y entra al portal con un solo acceso. El **proyecto es el trabajo que se hace**, no el expediente del cliente: por eso los accesos y los activos no cuelgan de él, que termina, sino del cliente y del dominio, que perduran.

El **cobro no es binario**: cada `Charge` lleva sus abonos (`ChargePayment`) y su estatus —pendiente, parcial, pagado, vencido— se deriva de ellos, porque los pagos en dos o tres exhibiciones están por todas partes en los datos reales y el restante es la columna que más se mira. Marcar un cobro como pagado registra el restante como un abono, no toca el estatus a mano.

La **agencia es de dónde viene el trabajo y quién paga**: cada una declara si se le factura a ella o al cliente final, y el listado reporta por agencia cuánto se cobró y cuánto falta.

El razonamiento completo detrás de estas decisiones está en **[CRM_PLAN.md](CRM_PLAN.md)**.

## Estado del proyecto

- ✅ Fase 0 — Roles y fundamento de auth
- ✅ Fase 1 — CRM de clientes y prospectos
- ✅ Fase 2 — Proyectos, servicios y cobros con recordatorios
- ✅ Fase 3 — Agencias colaboradoras
- ✅ Fase 4 — Portal de clientes (proyectos, cobros y correos propios, de solo lectura)
- 🔶 Fase 5 — Aprovisionamiento de correo (andamiaje completo con driver simulado; falta conectar MXroute/cPanel/Hostinger reales)
- ✅ Fase 6 — Dashboard interno con KPIs por rol
- ✅ Fase 7 — Dominios, tipos de proyecto y campañas de ads
- ✅ Fase 8 — Contactos como entidad propia
- ✅ Fase 9 — Accesos de servidor, licencias e importación del libro de hosting
- ✅ Fase 10 — Abonos y pagos parciales, cobros editables y agencias reorientadas
- 📋 Fases 11–14 — Líneas cobrables sin proyecto con subtareas, renovaciones con aviso al cliente, cotizaciones y contratos

## Desarrollo local

Este proyecto corre con **[LERD](https://github.com/geodro/lerd)** (alternativa open source a Laravel Herd basada en Podman), no con `php artisan serve`, Sail ni Valet.

- Dominio local: `https://sites.test` (definido en `.lerd.yaml`)
- Servicios: MySQL, Mailpit
- CLI: `lerd db shell`, `lerd console` (artisan dentro del contenedor), `lerd check`

### Instalación

```bash
composer install
```

```bash
pnpm install
```

```bash
cp .env.example .env && php artisan key:generate
```

### Base de datos

```bash
php artisan migrate:fresh --seed
```

Las migraciones se **reescriben en su lugar** en vez de acumular migraciones de `add_x_to_y`: el proyecto todavía no tiene producción ni datos que preservar, así que el esquema de cada tabla se lee entero en el archivo que la crea. Esto deja de aplicar en cuanto haya un despliegue real.

El seeder construye seis escenarios con nombre en vez de repetir el mismo proyecto genérico, para que abrir la app muestre el sistema completo: un proyecto web con dominio, buzones y accesos; un mantenimiento trimestral con un dominio de solo seguimiento; campañas de ads con las dos formas de facturar el presupuesto; un proyecto solo de correo; un rediseño a plazos en dólares; y un proyecto cancelado con agencia heredada. Entre todos cubren las seis frecuencias de cobro, las nueve categorías de servicio y los cuatro estatus de cobro, incluido uno abonado a la mitad.

La cuenta de administrador real se siembra aparte y necesita `ADMIN_SEED_PASSWORD` en el `.env`:

```bash
php artisan db:seed --class=AdminSeeder
```

### Usuarios de prueba

Password para todos: `password`

| Email | Rol |
|---|---|
| `test@example.com` | Admin |
| `staff@example.com` | Equipo interno (staff) |
| `colaborador@example.com` | Colaborador externo |
| `cliente@example.com` | Cliente — Juan Pérez, dueño de tres empresas, útil para ver el portal multi-empresa |

### Assets del frontend

```bash
pnpm run dev
```

```bash
pnpm run build
```

## Roles y accesos

| Rol | Alcance |
|---|---|
| **Admin** | Acceso total, incluidos los accesos de servidor, las credenciales de licencia y los proveedores de correo. |
| **Staff** (equipo interno) | Todo el CRM —clientes, contactos, proyectos, cobros, dominios, buzones— **menos** contraseñas de servidor y de licencia. |
| **Collaborator** (colaborador externo) | Solo los proyectos que se le asignen. Sin datos financieros, sin dominios, sin campañas. |
| **Client** (cliente) | Portal de solo lectura: los proyectos, cobros y buzones de todas sus empresas. |

## Datos sensibles

El sistema custodia credenciales de clientes, así que el criterio es explícito:

- **Todo se guarda cifrado** con el cast `encrypted`: contraseñas de buzón, de servidor y de licencia, y las credenciales de proveedor.
- **Los accesos de servidor son solo de admin** y **nunca aparecen en el portal**. Un cPanel o una base de datos abren la infraestructura del cliente; su buzón, en cambio, sí es suyo y puede verlo.
- **La contraseña de un buzón solo se guarda si el proveedor no tiene API.** Un driver real puede resetearla cuando sea; uno manual no, y perderla ahí es perderla para siempre.
- **Los correos al cliente llevan enlace al portal, nunca las contraseñas en el cuerpo.**

## Comandos útiles

```bash
php artisan test --compact
```

```bash
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

```bash
vendor/bin/pint --dirty --format agent
```

Corrida diaria vía scheduler: genera los cobros programados, marca los vencidos y envía los recordatorios de cobro y de expiración de dominios.

```bash
php artisan charges:process
```

Importa dominios, buzones y accesos desde el libro de hosting en Excel. Es idempotente, y la corrida en seco recorre el camino real dentro de una transacción que deshace al final.

```bash
php artisan import:hosting archivo.xlsx --dry-run
```

## Documentación del proyecto

- [CRM_PLAN.md](CRM_PLAN.md) — historial de fases, decisiones de producto con su razonamiento, modelo objetivo y hoja de ruta.
- `.ai/rules/` (si existe) — convenciones y reglas del proyecto para Laravel Boost.
