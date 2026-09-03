<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property ClientType $type
 * @property ClientStatus $status
 * @property string $name
 * @property string|null $company_name
 * @property string|null $source
 * @property int|null $assigned_to_user_id
 * @property int|null $agency_id
 * @property string $currency
 * @property CarbonImmutable|null $won_at
 * @property string|null $lost_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'status', 'name', 'company_name', 'source', 'assigned_to_user_id', 'agency_id', 'currency'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'status' => ClientStatus::class,
            'won_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Las personas de contacto de esta empresa. La misma persona puede ser
     * contacto de varias empresas, así que los datos viven en `contacts` y no
     * duplicados en cada cliente.
     *
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps()
            ->orderByDesc('client_contact.is_primary')
            ->orderBy('contacts.name');
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts->firstWhere('pivot.is_primary', true) ?? $this->contacts->first();
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<ClientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Mailboxes reach a client through their domain: a mailbox belongs to
     * `acme.com`, and `acme.com` belongs to the client.
     *
     * @return HasManyThrough<EmailAccount, Domain, $this>
     */
    public function emailAccounts(): HasManyThrough
    {
        return $this->hasManyThrough(EmailAccount::class, Domain::class);
    }
}
