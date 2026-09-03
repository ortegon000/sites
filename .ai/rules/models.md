---
paths:
  - 'app/{Models/Domain.php,Livewire/DomainsPanel.php,Models/Project.php}'
  - 'app/{Models/Agency.php,Models/Project.php,Models/Client.php}'
---

# Models

## El correo se administra desde el dominio, nunca desde el proyecto
`domains.email_management` es el único lugar donde se declara si nosotros llevamos los buzones. El proyecto no participa: la columna `projects.includes_email` se eliminó, junto con el checkbox del formulario de proyecto, el aviso del detalle y el valor que el proyecto proponía al dar de alta un dominio.

La razón: la mayoría de los dominios con buzones son de clientes de puro hosting y renovación, sin proyecto abierto. Colgar el correo del proyecto dejaba fuera el caso más común y obligaba a mantener dos fuentes de verdad.

No vuelvas a agregar banderas de correo a nivel proyecto. Si algo necesita saber si administramos el correo, pregúntaselo al dominio (`$domain->managesEmail()`).

## La agencia se relaciona con el cliente; el proyecto la hereda
`clients.agency_id` es la única relación con una agencia. El proyecto pertenece a la agencia de su cliente y a ninguna otra: se eliminaron la tabla pivote `agency_project`, la tarjeta de agencias del detalle del proyecto y la acción `SyncClientAgencyToProjects`. `Agency::projects()` es un hasManyThrough por clientes, y para filtrar proyectos por agencia se usa `whereHas('client', fn ($q) => $q->where('agency_id', ...))`.

Tampoco existe la facturación en doble sentido: se quitó `agencies.billing_target` (y su enum). Nunca cambió ningún cálculo, solo pintaba una insignia, y a quién se le cobra ya lo dice el cliente al que cuelga la línea.

Ojo al contar por agencia: `Agency::withCount('projects')` pasa por `clients`, que también tiene columna `status`, así que califica la columna (`projects.status`).
