<?php

namespace App\Livewire;

use App\Actions\EmailAccounts\ChangeEmailAccountPassword;
use App\Actions\EmailAccounts\DeleteEmailAccount;
use App\Actions\EmailAccounts\ImportEmailAccounts;
use App\Actions\EmailAccounts\ProvisionEmailAccount;
use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\EmailProviderStatus;
use App\Models\Domain;
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
 * Domains of a project, and the mailboxes hanging off each of them. Lives as
 * its own component rather than inside `pages::projects.show` because that
 * page already carries services, team, charges and agencies — and because the
 * mailbox forms bring their own modals and validation.
 */
class ProjectDomains extends Component
{
    public Project $project;

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

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;
    }

    /**
     * @return Collection<int, Domain>
     */
    #[Computed]
    public function domains(): Collection
    {
        return $this->project->domains()
            ->with(['emailAccounts.provider'])
            ->orderBy('name')
            ->get();
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
        Gate::authorize('update', $this->project);

        $this->resetValidation();
        $this->editingDomainId = $domainId;

        if ($domainId === null) {
            $this->domainName = '';
            $this->management = DomainManagement::Managed->value;
            $this->registrar = null;
            $this->registeredAt = null;
            $this->expiresAt = null;
            $this->autoRenew = true;
            $this->emailManagement = DomainEmailManagement::NotManaged->value;
            $this->emailNotes = null;
            $this->domainStatus = DomainStatus::Activo->value;
        } else {
            $domain = $this->project->domains()->findOrFail($domainId);

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
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'domainName' => [
                'required', 'string', 'max:255',
                Rule::unique('domains', 'name')
                    ->where('client_id', $this->project->client_id)
                    ->ignore($this->editingDomainId),
            ],
            'management' => ['required', Rule::enum(DomainManagement::class)],
            'registrar' => ['nullable', 'string', 'max:255'],
            'registeredAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date'],
            'autoRenew' => ['boolean'],
            'emailManagement' => ['required', Rule::enum(DomainEmailManagement::class)],
            'emailNotes' => ['nullable', 'string', 'max:1000'],
            'domainStatus' => ['required', Rule::enum(DomainStatus::class)],
        ]);

        if ($validated['emailManagement'] === DomainEmailManagement::Managed->value && ! $this->project->includes_email) {
            $this->addError('emailManagement', __('Este proyecto no incluye correo. Actívalo en el proyecto para poder administrar buzones en este dominio.'));

            return;
        }

        $attributes = [
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
            'name' => $validated['domainName'],
            'management' => DomainManagement::from($validated['management']),
            'registrar' => $validated['registrar'],
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
            $this->project->domains()->findOrFail($this->editingDomainId)->update($attributes);
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
        Gate::authorize('update', $this->project);

        $this->project->domains()->findOrFail($domainId)->delete();

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Dominio eliminado.'));
    }

    public function openEmailModal(int $domainId): void
    {
        Gate::authorize('update', $this->project);

        $this->resetValidation();
        $this->emailDomainId = $domainId;
        $this->emailProviderIdToAssign = null;
        $this->newEmailAddress = '';
        $this->newEmailPassword = '';

        $this->modal('email-account-form')->show();
    }

    public function provisionEmailAccount(ProvisionEmailAccount $action): void
    {
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'emailDomainId' => ['required', 'integer'],
            'emailProviderIdToAssign' => ['required', 'exists:email_providers,id'],
            'newEmailAddress' => ['required', 'email', 'max:255', 'unique:email_accounts,email_address'],
            'newEmailPassword' => ['required', 'string', 'min:8'],
        ]);

        $domain = $this->project->domains()->findOrFail((int) $validated['emailDomainId']);

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
        Gate::authorize('update', $this->project);

        $action->handle($this->findEmailAccount($emailAccountId));

        unset($this->domains);

        Flux::toast(variant: 'success', text: __('Cuenta de correo eliminada.'));
    }

    public function openImportModal(int $domainId): void
    {
        Gate::authorize('update', $this->project);

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
        Gate::authorize('update', $this->project);

        $validated = $this->validate([
            'importDomainId' => ['required', 'integer'],
            'importProviderId' => ['required', 'exists:email_providers,id'],
        ]);

        $domain = $this->project->domains()->findOrFail((int) $validated['importDomainId']);
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
        Gate::authorize('update', $this->project);

        $domain = $this->project->domains()->findOrFail((int) $this->importDomainId);
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

    public function openPasswordModal(int $emailAccountId): void
    {
        Gate::authorize('update', $this->project);

        $this->passwordAccountId = $emailAccountId;
        $this->newPassword = '';
        $this->resetValidation();

        $this->modal('email-password-form')->show();
    }

    public function changePassword(ChangeEmailAccountPassword $action): void
    {
        Gate::authorize('update', $this->project);

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
            ->whereIn('domain_id', $this->project->domains()->select('domains.id'))
            ->findOrFail($emailAccountId);
    }

    public function render(): View
    {
        return view('livewire.project-domains');
    }
}
