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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property DomainManagement $management
 * @property string|null $registrar
 * @property string|null $site_url
 * @property string|null $hosting_plan
 * @property CarbonImmutable|null $hosted_since
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
#[Fillable(['client_id', 'name', 'management', 'registrar', 'site_url', 'hosting_plan', 'hosted_since', 'registered_at', 'expires_at', 'auto_renew', 'email_management', 'email_notes', 'status', 'expiry_notified_at'])]
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
            'hosted_since' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'expiry_notified_at' => 'datetime',
        ];
    }

    /**
     * Que administremos el correo es propiedad del dominio y de nadie más.
     *
     * Antes esto exigía además un proyecto marcado como "incluye correo", que
     * tenía sentido cuando se asumía que todo cliente tenía proyecto. Los datos
     * reales dijeron lo contrario: la mayoría de los dominios con buzones son
     * de clientes que solo tienen hosting y renovación, sin proyecto abierto, y
     * esa regla los dejaba con sus buzones invisibles. Esa marca del proyecto ya
     * no existe: el correo se administra desde el dominio, sin pasar por ningún
     * proyecto.
     */
    public function managesEmail(): bool
    {
        return $this->email_management === DomainEmailManagement::Managed;
    }

    /**
     * @return MorphMany<Renewal, $this>
     */
    public function renewals(): MorphMany
    {
        return $this->morphMany(Renewal::class, 'renewable');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<EmailAccount, $this>
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * @return HasMany<DomainCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(DomainCredential::class);
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
