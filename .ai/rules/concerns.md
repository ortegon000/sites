---
paths:
  - app/Concerns/ManagesProjectForm.php
---

# Concerns

## El formulario de proyecto se comparte entre el listado y la ficha del cliente
Dar de alta un proyecto se hace en el mismo modal desde dos lados: el listado general (`pages/projects/⚡index`) y la tarjeta de proyectos de la ficha del cliente (`App\Livewire\ProjectsPanel`). El estado, la validación y el guardado viven en el trait `App\Concerns\ManagesProjectForm`; el markup, en `resources/views/components/project-form-modal.blade.php`. Si agregas un campo, va en los dos, no en una copia.

Quien ya sabe de qué cliente se trata sobrescribe `lockedProjectClientId()`: ahí el selector de cliente no se pinta y ese id manda sobre lo que llegue del navegador. También sobrescribe `findProjectToEdit()` para buscar dentro del alcance del panel.

Al probar la ficha con `Livewire::test('pages::clients.show')`, lo que pintan los paneles hijos solo se ve en el render inicial: para asertar sobre la tabla de proyectos, monta `ProjectsPanel` directo.
