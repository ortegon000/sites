---
paths:
  - 'app/Models/Quote.php,routes/web.php,resources/views/pages/quotes/public.blade.php'
---

# Pages Quotes

## El enlace público de una cotización usa public_token, generado en el constructor
Cada `Quote` tiene `public_token` (40 caracteres al azar, único, no está en `#[Fillable]`) para su enlace público en `/c/{token}` (ruta `quotes.public`, sin middleware de auth, definida en `routes/web.php`). El token se genera en el **constructor** de `Quote` (`! $this->exists && $this->public_token === null`), no en un evento `creating`: `DatabaseSeeder` usa `WithoutModelEvents`, así que un evento ahí nunca correría durante el seed y todo `Quote::factory()->create()` fallaría por `public_token` sin valor.

La página pública (`pages::quotes.public`, layout `layouts::quote-public`) NO usa `App\Concerns\ManagesQuoteActions` -ese trait autoriza contra `$quote->client` vía `Gate`, que requiere un usuario autenticado, y aquí no hay ninguno-. Tiene su propio `accept()`/`reject()` que llaman a `AcceptQuote`/`RejectQuote` sin actor (por eso `AcceptQuote::handle()` y `ChangeClientStatus::handle()` aceptan `?User $actor = null`: cuando acepta el propio cliente, la nota de cambio de estatus del prospecto queda sin autor). La página solo deja aceptar/rechazar cuando `status === Enviada`; para cualquier otro estatus muestra un mensaje según el caso (aceptada/rechazada/no disponible) en vez del formulario. Nunca pregunta "línea suelta o proyecto": esa decisión la toma el equipo después, no el cliente.

`ManagesQuoteActions::quotePublicUrl()` es lo que arma este enlace, y es lo único que copia el botón de copiar en cada renglón (sin texto alrededor). Pasa el token explícito a `route(..., absolute: false)`, no el modelo, y antepone `config('app.url')` en vez de dejar que Laravel use el host de la petición -en LERD, y detrás de cualquier proxy, ese host puede no ser el dominio público.
