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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property ClientType $type
 * @property ClientStatus $status
 * @property string $name
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $source
 * @property int|null $assigned_to_user_id
 * @property int|null $agency_id
 * @property string $currency
 * @property CarbonImmutable|null $won_at
 * @property string|null $lost_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'status', 'name', 'company_name', 'contact_name', 'email', 'phone', 'source', 'assigned_to_user_id', 'agency_id', 'currency'])]
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
     * @return HasMany<ClientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
