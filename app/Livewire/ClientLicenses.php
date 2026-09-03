<?php

namespace App\Livewire;

use App\Enums\LicenseStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\License;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Licencias y suscripciones del cliente: Brevo, Elementor, una cuenta de
 * WhatsApp Business, el tema de un sitio.
 *
 * Cuelgan del cliente y no del dominio porque muchas no son de un sitio en
 * particular, aunque las que sí lo son pueden ligarse a uno. Como todo activo
 * tienen fecha de renovación, y de ahí sale el aviso de caducidad.
 */
class ClientLicenses extends Component
{
    public Client $client;

    public ?int $editingLicenseId = null;

    public string $name = '';

    public ?string $vendor = null;

    public ?int $domainId = null;

    public ?string $url = null;

    public ?string $username = null;

    public ?string $password = null;

    public ?string $cost = null;

    public string $currency = 'MXN';

    public ?string $renewalDate = null;

    public bool $autoRenew = false;

    public string $status = '';

    public ?string $notes = null;

    /**
     * @var array<int, string>
     */
    public array $revealedPasswords = [];

    public function mount(Client $client): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->currency = $client->currency;
    }

    /**
     * @return Collection<int, License>
     */
    #[Computed]
    public function licenses(): Collection
    {
        return $this->client->licenses()
            ->with('domain')
            ->orderBy('renewal_date')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Domain>
     */
    #[Computed]
    public function assignableDomains(): Collection
    {
        return $this->client->domains()->orderBy('name')->get();
    }

    /**
     * @return array<int, LicenseStatus>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return LicenseStatus::cases();
    }

    /**
     * Una licencia guarda credenciales del proveedor, así que se rige por el
     * mismo criterio que los accesos de servidor: solo admin.
     */
    #[Computed]
    public function canSeeCredentials(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function openLicenseModal(?int $licenseId = null): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->editingLicenseId = $licenseId;

        if ($licenseId === null) {
            $this->name = '';
            $this->vendor = null;
            $this->domainId = null;
            $this->url = null;
            $this->username = null;
            $this->password = null;
            $this->cost = null;
            $this->currency = $this->client->currency;
            $this->renewalDate = null;
            $this->autoRenew = false;
            $this->status = LicenseStatus::Activa->value;
            $this->notes = null;
        } else {
            $license = $this->client->licenses()->findOrFail($licenseId);

            $this->name = $license->name;
            $this->vendor = $license->vendor;
            $this->domainId = $license->domain_id;
            $this->url = $license->url;
            $this->username = $license->username;
            $this->password = $this->canSeeCredentials() ? $license->password : null;
            $this->cost = $license->cost;
            $this->currency = $license->currency;
            $this->renewalDate = $license->renewal_date?->toDateString();
            $this->autoRenew = $license->auto_renew;
            $this->status = $license->status->value;
            $this->notes = $license->notes;
        }

        $this->modal('license-form')->show();
    }

    public function saveLicense(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'domainId' => ['nullable', Rule::exists('domains', 'id')->where('client_id', $this->client->id)],
            'url' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'renewalDate' => ['nullable', 'date'],
            'autoRenew' => ['boolean'],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'vendor' => $validated['vendor'],
            'domain_id' => $validated['domainId'],
            'url' => $validated['url'],
            'username' => $validated['username'],
            'cost' => $validated['cost'],
            'currency' => $validated['currency'],
            'renewal_date' => $validated['renewalDate'],
            'auto_renew' => $validated['autoRenew'],
            'status' => LicenseStatus::from($validated['status']),
            'notes' => $validated['notes'],
        ];

        /** Solo quien puede ver contraseñas puede escribirlas, y un campo vacío no borra la guardada. */
        if ($this->canSeeCredentials() && filled($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        if ($this->editingLicenseId === null) {
            $this->client->licenses()->create($attributes);
        } else {
            $this->client->licenses()->findOrFail($this->editingLicenseId)->update($attributes);
        }

        unset($this->licenses);
        $this->revealedPasswords = [];

        $this->modal('license-form')->close();

        Flux::toast(variant: 'success', text: __('Licencia guardada.'));
    }

    public function closeLicenseModal(): void
    {
        $this->modal('license-form')->close();
    }

    public function deleteLicense(int $licenseId): void
    {
        Gate::authorize('update', $this->client);

        $this->client->licenses()->findOrFail($licenseId)->delete();

        unset($this->licenses);

        Flux::toast(variant: 'success', text: __('Licencia eliminada.'));
    }

    public function revealPassword(int $licenseId): void
    {
        abort_unless($this->canSeeCredentials(), 403);

        $license = $this->client->licenses()->findOrFail($licenseId);

        if ($license->password !== null) {
            $this->revealedPasswords[$licenseId] = $license->password;
        }
    }

    public function hidePassword(int $licenseId): void
    {
        unset($this->revealedPasswords[$licenseId]);
    }

    public function render(): View
    {
        return view('livewire.client-licenses');
    }
}
