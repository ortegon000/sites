---
paths:
  - 'app/{Concerns/ManagesQuoteActions.php,Livewire/QuotesPanel.php},resources/views/pages/quotes/**,resources/views/partials/quotes/**'
---

# Partials Quotes

## Las acciones de una cotización viven en App\Concerns\ManagesQuoteActions
Editar, enviar, aceptar, rechazar (y deshacer cualquiera de esas tres), copiar y borrar una cotización ya existente viven en el trait `App\Concerns\ManagesQuoteActions`, no en `QuotesPanel` ni en la página de `/cotizaciones` por separado — mismo patrón que `App\Concerns\ManagesProjectForm` para proyectos. Lo usan ambos: `QuotesPanel` (acotado a un cliente y quizás a un proyecto) y la página `pages::quotes.index` (sin acotar a nadie). Cada acción autoriza contra `$quote->client`, no contra un `$this->client` fijo, porque el listado general no tiene uno.

Quien agregue una acción nueva sobre una cotización existente la agrega ahí, no en los componentes — si no, el listado general y el panel se desincronizan otra vez. Los hooks que cada host sobrescribe: `findQuoteForAction()` (`QuotesPanel` acota por cliente/proyecto; el listado general usa el default `Quote::findOrFail()`), `refreshQuoteLists()` (invalida los computed que haya que refrescar) y, solo si el host sabe crear cotizaciones desde cero, `newQuoteClient()`/`newQuoteProjectId()` (el listado general nunca los llama: "Nueva cotización" ahí resuelve el cliente primero y redirige a su ficha).

Las vistas de los tres modales (`quote-form`, `quote-accept`, `quote-rejection`) y de las acciones por renglón viven en `resources/views/partials/quotes/*.blade.php`, incluidas con `@include` (no `<x-component>`, porque las acciones llaman a `$this->quoteSummary($quote)` y `$this` solo se resuelve correctamente contra el componente Livewire dentro de un `@include`, no dentro de un Blade component con scope propio). `quotes-panel.blade.php` y `pages/quotes/⚡index.blade.php` las incluyen igual.
