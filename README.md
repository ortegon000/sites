# CRM de Agencia

Aplicación interna a medida para gestionar clientes, prospectos, proyectos, cobros recurrentes, agencias colaboradoras y cuentas de correo de clientes.

Construida sobre Laravel 13 con el stack de [`laravel/livewire-starter-kit`](https://github.com/laravel/livewire-starter-kit): Livewire 4 (componentes de página nativos), [Flux UI](https://fluxui.dev/), Fortify (login, 2FA, passkeys) y Tailwind CSS v4.

## Estado del proyecto

El desarrollo avanza por fases. El detalle completo, las decisiones de producto confirmadas y el roadmap están en **[CRM_PLAN.md](CRM_PLAN.md)**.

- ✅ Fase 0 — Roles y fundamento de auth
- ✅ Fase 1 — CRM de clientes y prospectos
- ✅ Fase 2 — Proyectos, servicios y cobros con recordatorios
- ✅ Fase 3 — Agencias colaboradoras
- ✅ Fase 4 — Portal de clientes (proyectos, cobros y correos propios, todo de solo lectura)
- 🔶 Fase 5 — Aprovisionamiento de cuentas de correo (andamiaje completo con driver simulado; falta conectar MXroute/cPanel/Hostinger reales)

## Desarrollo local

Este proyecto corre con **[LERD](https://github.com/geodro/lerd)** (alternativa open source a Laravel Herd basada en Podman), no con `php artisan serve`, Sail ni Valet.

- Dominio local: `https://sites.test` (definido en `.lerd.yaml`)
- Servicios: MySQL, Mailpit
- CLI: `lerd db shell`, `lerd console` (artisan dentro del contenedor), `lerd check`

### Instalación

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
```

### Base de datos

```bash
php artisan migrate:fresh --seed
```

El seeder (`database/seeders/`) crea datos de ejemplo: clientes, prospectos, notas de actividad, un usuario por rol, proyectos con servicios y cobros en distintos estados (pendiente, pagado, vencido), agencias colaboradoras asociadas a proyectos, y un proveedor de correo simulado con un par de cuentas ya aprovisionadas.

### Usuarios de prueba

Password para todos: `password`

| Email | Rol |
|---|---|
| `test@example.com` | Admin |
| `staff@example.com` | Equipo interno (staff) |
| `colaborador@example.com` | Colaborador externo |
| `cliente@example.com` | Cliente (portal) |

### Assets del frontend

```bash
pnpm run dev    # servidor de desarrollo con HMR
pnpm run build  # build de producción
```

## Roles y accesos

| Rol | Alcance |
|---|---|
| **Admin** | Acceso total. |
| **Staff** (equipo interno) | Acceso total al CRM (clientes, prospectos, proyectos, cobros). |
| **Collaborator** (colaborador externo) | Solo ve los proyectos que se le asignen. Sin acceso a datos financieros ni a otros clientes. |
| **Client** (cliente) | Portal limitado: sus propios proyectos, cobros y cuentas de correo. |

## Comandos útiles

```bash
# Pruebas (Pest)
php artisan test --compact
php artisan test --filter=nombreDelTest

# Análisis estático (Larastan, nivel 7)
vendor/bin/phpstan analyse --no-progress --memory-limit=512M

# Formato de código (Pint)
vendor/bin/pint --format agent <rutas>

# Rutas registradas
php artisan route:list

# Generar cobros pendientes, marcar vencidos, y enviar recordatorios (corre diario vía scheduler)
php artisan charges:process
```

## Documentación del proyecto

- [CRM_PLAN.md](CRM_PLAN.md) — plan de implementación completo, decisiones de producto y roadmap por fases.
- `.ai/rules/` (si existe) — convenciones y reglas específicas del proyecto para Laravel Boost.
