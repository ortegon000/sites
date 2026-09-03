<?php

namespace App\Livewire;

use App\Actions\EmailAccounts\ChangeEmailAccountPassword;
use App\Actions\EmailAccounts\DeleteEmailAccount;
use App\Actions\EmailAccounts\ImportEmailAccounts;
use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\DomainCredentialKind;
use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\EmailProviderStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainCredential;
use App\Models\EmailAccount;
use App\Models\EmailProvider;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Los dominios de un cliente con lo que cuelga de cada uno: buzones y accesos
 * técnicos.
 *
 * Recibe siempre el cliente —que es el dueño del dominio— y opcionalmente un
 * proyecto. Con proyecto se acota a los dominios de ese proyecto y sirve de
 * tarjeta en su detalle; sin él lista todos los del cliente, que es la única
 * forma de administrar los dominios de quien solo tiene hosting y ningún
 * proyecto abierto.
 */
class DomainsPanel extends Component
{
    public Client $client;

    public ?Project $project = null;

    public ?int $editingDomainId = null;

    public string $domainName = '';

    public string $management = '';

    public ?string $registrar = null;

    public ?string $registeredAt = null;

    public ?string $expiresAt = null;

    public bool $autoRenew = false;

    public string $emailManagement = '';

    public ?string $emailNotes = null;

    public string $domainStatus = '';

    public ?int $domainProjectId = null;

    public ?string $siteUrl = null;

    public ?string $hostingPlan = null;

    public ?string $hostedSince = null;

    public ?int $credentialDomainId = null;

    public ?int $editingCredentialId = null;

    public string $credentialKind = '';

    public ?string $credentialLabel = null;

    public ?string $credentialUrl = null;

    public ?string $credentialUsername = null;

    public ?string $credentialPassword = null;

    public ?string $credentialNotes = null;

    /**
     * Contraseñas que el usuario pidió ver, por id de acceso. Se cargan al
     * pulsar el botón para que no viajen en el render inicial de la página.
     *
     * @var array<int, string>
     */
    public array $revealedCredentials = [];

    public ?int $emailDomainId = null;

    public ?int $emailProviderIdToAssign = null;

    public string $newEmailAddress = '';

    public string $newEmailPassword = '';

    public ?int $importDomainId = null;

    public ?int $importProviderId = null;

    /**
     * Addresses the provider reports for the domain, and the subset the user
     * ticked. Only the ticked ones are registered — a provider may hold
     * mailboxes the agency does not track.
     *
     * @var array<int, string>
     */
    public array $importCandidates = [];

    /**
     * @var array<int, string>
     */
    public array $selectedImports = [];

    public ?int $passwordAccountId = null;

    public string $newPassword = '';

    public function mount(Client $client, ?Project $project = null): void
    {
        Gate::authorize('view', $client);

        $this->client = $client;
        $this->project = $project;
    }

    /**
     * Los accesos de servidor abren la infraestructura del cliente, no solo su
     * correo, así que se guardan bajo el mismo criterio que las credenciales de
     * proveedor: únicamente admin.
     */
    #[Computed]
    public function canSeeCredentials(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * @return Collection<int, Domain>
     */
    #[Computed]
    public function domains(): Collection
    {
        return ($this->project?->domains() ?? $this->client->domains())
            ->with(['emailAccounts.provider', 'credentials', 'project'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function assignableProjects(): Collection
    {
        return $this->client->projects()->orderBy('name')->get();
    }

    /**
     * @return array<int, DomainCredentialKind>
     */
    #[Computed]
    public function credentialKindOptions(): array
    {
        return DomainCredentialKind::cases();
    }

    /**
     * @return Collection<int, EmailProvider>
     */
    #[Computed]
    public function activeEmailProviders(): Collection
    {
        return EmailProvider::query()
            ->where('status', EmailProviderStatus::Activo)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, DomainManagement>
     */
    #[Computed]
    public function managementOptions(): array
    {
        return DomainManagement::cases();
    }

    /**
     * @return array<int, DomainStatus>
     */
    #[Computed]
    public function domainStatusOptions(): array
    {
        return DomainStatus::cases();
    }

    public function openDomainModal(?int $domainId = null): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->editingDomainId = $domainId;

        if ($domainId === null) {
            /** Dentro de un proyecto se propone ese; desde el cliente, ninguno. */
            $this->domainProjectId = $this->project?->id;
            $this->siteUrl = null;
            $this->hostingPlan = null;
            $this->hostedSince = null;
            $this->domainName = '';
            $this->management = DomainManagement::Managed->value;
            $this->registrar = null;
            $this->registeredAt = null;
            $this->expiresAt = null;
            $this->autoRenew = true;
            /** Un proyecto que incluye correo propone administrarlo; es una propuesta, no un candado. */
            $this->emailManagement = $this->project?->includes_email
                ? DomainEmailManagement::Managed->value
                : DomainEmailManagement::NotManaged->value;
            $this->emailNotes = null;
            $this->domainStatus = DomainStatus::Activo->value;
        } else {
            $domain = $this->client->domains()->findOrFail($domainId);

            $this->domainProjectId = $domain->project_id;
            $this->siteUrl = $domain->site_url;
            $this->hostingPlan = $domain->hosting_plan;
            $this->hostedSince = $domain->hosted_since?->toDateString();
            $this->domainName = $domain->name;
            $this->management = $domain->management->value;
            $this->registrar = $domain->registrar;
            $this->registeredAt = $domain->registered_at?->toDateString();
            $this->expiresAt = $domain->expires_at?->toDateString();
            $this->autoRenew = $domain->auto_renew;
            $this->emailManagement = $domain->email_management->value;
            $this->emailNotes = $domain->email_notes;
            $this->domainStatus = $domain->status->value;
        }

        $this->modal('domain-form')->show();
    }

    public function saveDomain(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'domainName' => [
                'required', 'string', 'max:255',
                Rule::unique('domains', 'name')
                    ->where('client_id', $this->client->id)
                    ->ignore($this->editingDomainId),
            ],
            'domainProjectId' => ['nullable', Rule::exists('projects', 'id')->where('client_id', $this->client->id)],
            'siteUrl' => ['nullable', 'string', 'max:255'],
            'hostingPlan' => ['nullable', 'string', 'max:255'],
            'hostedSince' => ['nullable', 'date'],
            'management' => ['required', Rule::enum(DomainManagement::class)],
            'registrar' => ['nullable', 'string', 'max:255'],
            'registeredAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date'],
            'autoRenew' => ['boolean'],
            'emailManagement' => ['required', Rule::enum(DomainEmailManagement::class)],
            'emailNotes' => ['nullable', 'string', 'max:1000'],
            'domainStatus' => ['required', Rule::enum(DomainStatus::class)],
        ]);

        $linkedProject = $validated['domainProjectId'] === null
            ? null
            : $this->client->projects()->find((int) $validated['domainProjectId']);

        $attributes = [
            'client_id' => $this->client->id,
            'project_id' => $linkedProject?->id,
            'name' => $validated['domainName'],
            'management' => DomainManagement::from($validated['management']),
            'registrar' => $validated['registrar'],
            'site_url' => $validated['siteUrl'],
            'hosting_plan' => $validated['hostingPlan'],
            'hosted_since' => $validated['hostedSince'],
            'registered_at' => $validated['registeredAt'],
            'expires_at' => $validated['expiresAt'],
            'auto_renew' => $validated['autoRenew'],
            'email_management' => DomainEmailManagement::from($validated['emailManagement']),
            'email_notes' => $validated['emailNotes'],
            'status' => DomainStatus::from($validated['domainStatus']),
        ];

        if ($this->editingDomainId === null) {
            Domain::create($attributes);
        } else {
            $this->client->domains()->findOrFail($this->editingDomainId)->update($attributes);
        }

        unset($this->domains);

        $this->modal('domain-form')->close();

        Flux::toast(variant: 'success', text: __('Dominio guardado.'));
    }

    public function closeDomainModal(): void
    {
        $this->modal('domain-form')->close();
    }

    public function deleteDomain(int $domainId): void
    {
        Gate::authorize('update', $this->client);

        $this->client->domains()->findOrFail($domainId)->delete();

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Dominio eliminado.'));
    }

    public function openEmailModal(int $domainId): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->emailDomainId = $domainId;
        $this->emailProviderIdToAssign = null;
        $this->newEmailAddress = '';
        $this->newEmailPassword = '';

        $this->modal('email-account-form')->show();
    }

    public function provisionEmailAccount(ProvisionEmailAccount $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'emailDomainId' => ['required', 'integer'],
            'emailProviderIdToAssign' => ['required', 'exists:email_providers,id'],
            'newEmailAddress' => ['required', 'email', 'max:255', 'unique:email_accounts,email_address'],
            'newEmailPassword' => ['required', 'string', 'min:8'],
        ]);

        $domain = $this->client->domains()->findOrFail((int) $validated['emailDomainId']);

        if (! $domain->managesEmail()) {
            $this->addError('emailDomainId', __('Este dominio no tiene el correo activado.'));

            return;
        }

        $action->handle(
            $domain,
            EmailProvider::findOrFail((int) $validated['emailProviderIdToAssign']),
            $validated['newEmailAddress'],
            $validated['newEmailPassword'],
        );

        unset($this->domains);

        $this->modal('email-account-form')->close();

        Flux::toast(variant: 'success', text: __('Cuenta de correo creada.'));
    }

    public function closeEmailModal(): void
    {
        $this->modal('email-account-form')->close();
    }

    public function deleteEmailAccount(int $emailAccountId, DeleteEmailAccount $action): void
    {
        Gate::authorize('update', $this->client);

        $action->handle($this->findEmailAccount($emailAccountId));

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Cuenta de correo eliminada.'));
    }

    public function openImportModal(int $domainId): void
    {
        Gate::authorize('update', $this->client);

        $this->resetValidation();
        $this->importDomainId = $domainId;
        $this->importProviderId = null;
        $this->importCandidates = [];
        $this->selectedImports = [];

        $this->modal('email-import')->show();
    }

    /**
     * Ask the provider which mailboxes already exist on the domain, leaving out
     * the ones already registered here.
     */
    public function loadImportCandidates(): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'importDomainId' => ['required', 'integer'],
            'importProviderId' => ['required', 'exists:email_providers,id'],
        ]);

        $domain = $this->client->domains()->findOrFail((int) $validated['importDomainId']);
        $provider = EmailProvider::findOrFail((int) $validated['importProviderId']);

        $known = $domain->emailAccounts()->pluck('email_address')->all();

        $this->importCandidates = array_values(array_diff(
            $provider->driver()->listMailboxes($provider, $domain->name),
            $known,
        ));

        $this->selectedImports = [];
    }

    public function importEmailAccounts(ImportEmailAccounts $action): void
    {
        Gate::authorize('update', $this->client);

        $domain = $this->client->domains()->findOrFail((int) $this->importDomainId);
        $provider = EmailProvider::findOrFail((int) $this->importProviderId);

        if ($this->selectedImports === []) {
            $this->addError('selectedImports', __('Selecciona al menos un buzón.'));

            return;
        }

        $imported = $action->handle($domain, $provider, $this->selectedImports);

        unset($this->domains);

        $this->modal('email-import')->close();

        Flux::toast(variant: 'success', text: trans_choice('Se vinculó :count buzón.|Se vincularon :count buzones.', $imported->count(), ['count' => $imported->count()]));
    }

    public function closeImportModal(): void
    {
        $this->modal('email-import')->close();
    }

    public function openCredentialModal(int $domainId, ?int $credentialId = null): void
    {
        Gate::authorize('update', $this->client);
        abort_unless($this->canSeeCredentials(), 403);

        $this->resetValidation();
        $this->credentialDomainId = $domainId;
        $this->editingCredentialId = $credentialId;

        if ($credentialId === null) {
            $this->credentialKind = DomainCredentialKind::Panel->value;
            $this->credentialLabel = null;
            $this->credentialUrl = null;
            $this->credentialUsername = null;
            $this->credentialPassword = null;
            $this->credentialNotes = null;
        } else {
            $credential = $this->findCredential($credentialId);

            $this->credentialKind = $credential->kind->value;
            $this->credentialLabel = $credential->label;
            $this->credentialUrl = $credential->url;
            $this->credentialUsername = $credential->username;
            $this->credentialPassword = $credential->password;
            $this->credentialNotes = $credential->notes;
        }

        $this->modal('credential-form')->show();
    }

    public function saveCredential(): void
    {
        Gate::authorize('update', $this->client);
        abort_unless($this->canSeeCredentials(), 403);

        $validated = $this->validate([
            'credentialDomainId' => ['required', 'integer'],
            'credentialKind' => ['required', Rule::enum(DomainCredentialKind::class)],
            'credentialLabel' => ['nullable', 'string', 'max:255'],
            'credentialUrl' => ['nullable', 'string', 'max:255'],
            'credentialUsername' => ['nullable', 'string', 'max:255'],
            'credentialPassword' => ['nullable', 'string', 'max:255'],
            'credentialNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $domain = $this->client->domains()->findOrFail((int) $validated['credentialDomainId']);

        $attributes = [
            'kind' => DomainCredentialKind::from($validated['credentialKind']),
            'label' => $validated['credentialLabel'],
            'url' => $validated['credentialUrl'],
            'username' => $validated['credentialUsername'],
            'password' => $validated['credentialPassword'],
            'notes' => $validated['credentialNotes'],
        ];

        if ($this->editingCredentialId === null) {
            $domain->credentials()->create($attributes);
        } else {
            $this->findCredential($this->editingCredentialId)->update($attributes);
        }

        unset($this->domains);
        $this->revealedCredentials = [];

        $this->modal('credential-form')->close();

        Flux::toast(variant: 'success', text: __('Acceso guardado.'));
    }

    public function closeCredentialModal(): void
    {
        $this->modal('credential-form')->close();
    }

    public function deleteCredential(int $credentialId): void
    {
        Gate::authorize('update', $this->client);
        abort_unless($this->canSeeCredentials(), 403);

        $this->findCredential($credentialId)->delete();

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Acceso eliminado.'));
    }

    public function revealCredential(int $credentialId): void
    {
        abort_unless($this->canSeeCredentials(), 403);

        $credential = $this->findCredential($credentialId);

        if ($credential->password !== null) {
            $this->revealedCredentials[$credentialId] = $credential->password;
        }
    }

    public function hideCredential(int $credentialId): void
    {
        unset($this->revealedCredentials[$credentialId]);
    }

    /**
     * Los accesos solo se alcanzan a través de los dominios de este cliente, así
     * que un id de otro cliente da 404 en vez de revelar una contraseña ajena.
     */
    private function findCredential(int $credentialId): DomainCredential
    {
        return DomainCredential::query()
            ->whereIn('domain_id', $this->client->domains()->select('domains.id'))
            ->findOrFail($credentialId);
    }

    public function openPasswordModal(int $emailAccountId): void
    {
        Gate::authorize('update', $this->client);

        $this->passwordAccountId = $emailAccountId;
        $this->newPassword = '';
        $this->resetValidation();

        $this->modal('email-password-form')->show();
    }

    public function changePassword(ChangeEmailAccountPassword $action): void
    {
        Gate::authorize('update', $this->client);

        $validated = $this->validate([
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $action->handle($this->findEmailAccount((int) $this->passwordAccountId), $validated['newPassword']);

        unset($this->domains);

        $this->modal('email-password-form')->close();

        Flux::toast(variant: 'success', text: __('Contraseña actualizada.'));
    }

    public function closePasswordModal(): void
    {
        $this->modal('email-password-form')->close();
    }

    /**
     * Mailboxes are only reachable through the domains of this project, so a
     * forged id from another project can never be acted on.
     */
    private function findEmailAccount(int $emailAccountId): EmailAccount
    {
        return EmailAccount::query()
            ->whereIn('domain_id', $this->client->domains()->select('domains.id'))
            ->findOrFail($emailAccountId);
    }

    public function render(): View
    {
        return view('livewire.domains-panel');
    }
}
