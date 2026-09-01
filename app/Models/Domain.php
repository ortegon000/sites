<?php

namespace App\Models;

use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property string $name
 * @property DomainManagement $management
 * @property string|null $registrar
 * @property CarbonImmutable|null $registered_at
 * @property CarbonImmutable|null $expires_at
 * @property bool $auto_renew
 * @property DomainEmailManagement $email_management
 * @property string|null $email_notes
 * @property DomainStatus $status
 * @property CarbonImmutable|null $expiry_notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'project_id', 'name', 'management', 'registrar', 'registered_at', 'expires_at', 'auto_renew', 'email_management', 'email_notes', 'status', 'expiry_notified_at'])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Renewing a domain pushes `expires_at` forward, which starts a new cycle:
     * clearing the stamp is what lets next year's reminder fire instead of the
     * domain going quiet after a single warning.
     */
    protected static function booted(): void
    {
        static::updating(function (Domain $domain): void {
            if ($domain->isDirty('expires_at')) {
                $domain->expiry_notified_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'management' => DomainManagement::class,
            'email_management' => DomainEmailManagement::class,
            'status' => DomainStatus::class,
            'registered_at' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'expiry_notified_at' => 'datetime',
        ];
    }

    /**
     * Email can only be turned on for a domain that is tied to a project which
     * includes email — either a dedicated email project or a web project that
     * bundles it. A domain with no project has nothing to bill the mailboxes
     * against, so it stays off.
     */
    public function canManageEmail(): bool
    {
        return $this->project?->includes_email === true;
    }

    public function managesEmail(): bool
    {
        return $this->email_management === DomainEmailManagement::Managed && $this->canManageEmail();
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<EmailAccount, $this>
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
