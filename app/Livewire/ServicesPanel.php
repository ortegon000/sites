<?php

namespace App\Livewire;

use App\Actions\Services\CancelService;
use App\Actions\Services\CreateServiceWithSchedule;
use App\Actions\Services\DeleteService;
use App\Enums\ServiceBillingFrequency;
use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Las líneas cobrables de un cliente: servicios recurrentes y trabajos.
 *
 * Recibe siempre el cliente —que es quien paga— y opcionalmente un proyecto.
 * Con proyecto lista las líneas de ese trabajo y sirve de tarjeta en su
 * detalle; sin él lista las líneas sueltas del cliente, que son la mayoría:
 * de unas setenta líneas cobrables al año, las que son proyectos de verdad son
 * cinco o seis.
 *
 * El renglón de captura rápida está siempre visible y sin modal a propósito:
 * si capturar una línea no es tan rápido como escribir una fila de Excel, se
 * vuelve al Excel.
 */
class ServicesPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public string $quickName = '';

    public string $quickAmount = '';

    public string $quickStartsOn = '';

    public string $quickFrequency = ServiceBillingFrequency::OneTime->value;

    public ?int $editingServiceId = null;

    public string $serviceName = '';

    public ?string $serviceDescription = null;

    public string $serviceCategory = ServiceCategory::Other->value;

    public ?int $serviceDomainId = null;

    public string $billingFrequency = '';

    public string $amount = '';

    public string $currency = 'MXN';

    public string $serviceStatus = '';

    public ?string $startsOn = null;

    public ?int $installmentsCount = null;

    public ?int $itemsServiceId = null;

    public string $itemDescription = '';

    public ?string $itemDueDate = null;

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
        $this->currency = $client->currency;
        $this->quickStartsOn = today()->toDateString();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->client->services()
            ->when($this->project, fn ($query) => $query->where('project_id', $this->project->id))
            ->when(! $this->project, fn ($query) => $query->whereNull('project_id'))
            ->with(['domain', 'items'])
            ->withCount(['items', 'items as pending_items_count' => fn ($query) => $query->whereNull('completed_at')])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function itemsService(): ?Service
    {
        if ($this->itemsServiceId === null) {
            return null;
        }

        return $this->client->services()
            ->with(['items' => fn ($query) => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')])
            ->find($this->itemsServiceId);
    }

    /**
     * @return array<int, ServiceBillingFrequency>
     */
    #[Computed]
    public function billingFrequencyOptions(): array
    {
        return ServiceBillingFrequency::cases();
    }

    /**
     * @return array<int, ServiceCategory>
     */
    #[Computed]
    public function serviceCategoryOptions(): array
    {
        return ServiceCategory::cases();
    }

    /**
     * @return array<int, ServiceStatus>
     */
    #[Computed]
    public function serviceStatusOptions(): array
    {
        return ServiceStatus::cases();
    }

    /**
     * @return Collection<int, Domain>
     */
    #[Computed]
    public function domainOptions(): Collection
    {
        return $this->client->domains()->orderBy('name')->get();
    }

    /**
     * Captura rápida: fecha, concepto y monto, con la frecuencia en pago único
     * salvo que se cambie. Todo lo demás toma su valor por omisión.
     */
    /**
     * La cotización que se acaba de aceptar dejó su línea cobrable aquí.
     */
    #[On('quote-accepted')]
    public function refreshServices(): void
    {
        unset($this->services);
    }

    public function quickCapture(CreateServiceWithSchedule $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'quickName' => ['required', 'string', 'max:255'],
            'quickAmount' => ['required', 'numeric', 'min:0'],
            'quickStartsOn' => ['required', 'date'],
            'quickFrequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
        ]);

        $frequency = ServiceBillingFrequency::from($validated['quickFrequency']);

        $action->handle($this->client, [
            'name' => $validated['quickName'],
            'category' => ServiceCategory::Other,
            'billing_frequency' => $frequency,
            'amount' => $validated['quickAmount'],
            'currency' => $this->client->currency,
            'status' => ServiceStatus::Activo,
            'starts_on' => $validated['quickStartsOn'],
            'installments_count' => null,
        ], $this->project);

        $this->reset(['quickName', 'quickAmount']);
        $this->quickFrequency = ServiceBillingFrequency::OneTime->value;
        $this->quickStartsOn = today()->toDateString();

        unset($this->services);

        Flux::toast(variant: 'success', text: __('Línea capturada.'));
    }

    public function openServiceModal(): void
    {
        Gate::authorize('update', $this->client);

        $this->reset(['serviceName', 'serviceDescription', 'billingFrequency', 'amount', 'installmentsCount', 'serviceDomainId']);
        $this->serviceCategory = ServiceCategory::Other->value;
        $this->currency = $this->client->currency;
        $this->serviceStatus = ServiceStatus::Activo->value;
        $this->startsOn = today()->toDateString();
        $this->resetValidation();

        $this->modal('service-form')->show();
    }

    public function saveService(CreateServiceWithSchedule $action): void
    {
        Gate::authorize('update', $this->client);

        $domainRule = Rule::exists('domains', 'id')->where('client_id', $this->client->id);

        $validated = $this->validate([
            'serviceName' => ['required', 'string', 'max:255'],
            'serviceDescription' => ['nullable', 'string', 'max:2000'],
            'serviceCategory' => ['required', Rule::enum(ServiceCategory::class)],
            'serviceDomainId' => ['nullable', $domainRule],
            'billingFrequency' => ['required', Rule::enum(ServiceBillingFrequency::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'serviceStatus' => ['required', Rule::enum(ServiceStatus::class)],
            'startsOn' => ['required', 'date'],
            'installmentsCount' => ['required_if:billingFrequency,'.ServiceBillingFrequency::Installment->value, 'nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $action->handle($this->client, [
            'name' => $validated['serviceName'],
            'description' => $validated['serviceDescription'],
            'category' => ServiceCategory::from($validated['serviceCategory']),
            'domain_id' => $validated['serviceDomainId'],
            'billing_frequency' => ServiceBillingFrequency::from($validated['billingFrequency']),
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => ServiceStatus::from($validated['serviceStatus']),
            'starts_on' => $validated['startsOn'],
            'installments_count' => $validated['billingFrequency'] === ServiceBillingFrequency::Installment->value
                ? $validated['installmentsCount']
                : null,
        ], $this->project);

        unset($this->services);

        $this->modal('service-form')->close();

        Flux::toast(variant: 'success', text: __('Servicio creado.'));
    }

    public function closeServiceModal(): void
    {
        $this->modal('service-form')->close();
    }

    public function cancelService(int $serviceId, CancelService $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findService($serviceId));

        unset($this->services);

        Flux::toast(variant: 'success', text: __('Servicio cancelado.'));
    }

    public function deleteService(int $serviceId, DeleteService $action): void
    {
        Gate::authorize('update', $this->client);

        $service = $this->findService($serviceId);

        if (! $service->canBeDeleted()) {
            Flux::toast(variant: 'danger', text: __('Este servicio ya tiene cobros con abonos. Cancélalo para detenerlo sin borrar el historial.'));

            return;
        }

        $action->handle($service);

        unset($this->services);

        Flux::toast(variant: 'success', text: __('Servicio eliminado.'));
    }

    public function openItemsModal(int $serviceId): void
    {
        Gate::authorize('view', $this->client);

        $this->itemsServiceId = $this->findService($serviceId)->id;
        $this->reset(['itemDescription', 'itemDueDate']);
        $this->resetValidation();

        $this->modal('service-items')->show();
    }

    public function addItem(): void
    {
        Gate::authorize('update', $this->client);

        $service = $this->findService($this->itemsServiceId ?? 0);

        $validated = $this->validate([
            'itemDescription' => ['required', 'string', 'max:255'],
            'itemDueDate' => ['nullable', 'date'],
        ]);

        $service->items()->create([
            'description' => $validated['itemDescription'],
            'due_date' => $validated['itemDueDate'],
        ]);

        $this->reset(['itemDescription', 'itemDueDate']);

        unset($this->services, $this->itemsService);

        Flux::toast(variant: 'success', text: __('Alcance actualizado.'));
    }

    public function toggleItem(int $itemId): void
    {
        Gate::authorize('update', $this->client);

        $item = $this->findItem($itemId);

        $item->update(['completed_at' => $item->isDone() ? null : now()]);

        unset($this->services, $this->itemsService);
    }

    public function deleteItem(int $itemId): void
    {
        Gate::authorize('update', $this->client);

        $this->findItem($itemId)->delete();

        unset($this->services, $this->itemsService);

        Flux::toast(variant: 'success', text: __('Se quitó del alcance de la línea.'));
    }

    public function closeItemsModal(): void
    {
        $this->itemsServiceId = null;

        $this->modal('service-items')->close();
    }

    /**
     * Los servicios se buscan siempre dentro del alcance del panel, así que un
     * id de otro cliente —o de otro proyecto— nunca se puede tocar desde aquí.
     */
    private function findService(int $serviceId): Service
    {
        return $this->client->services()
            ->when($this->project, fn ($query) => $query->where('project_id', $this->project->id))
            ->when(! $this->project, fn ($query) => $query->whereNull('project_id'))
            ->findOrFail($serviceId);
    }

    private function findItem(int $itemId): ServiceItem
    {
        return ServiceItem::query()
            ->where('service_id', $this->findService($this->itemsServiceId ?? 0)->id)
            ->findOrFail($itemId);
    }

    public function render(): View
    {
        return view('livewire.services-panel');
    }
}
