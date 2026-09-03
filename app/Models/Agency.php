<?php

namespace App\Models;

use App\Enums\AgencyBillingTarget;
use App\Enums\AgencyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property AgencyBillingTarget $billing_target
 * @property AgencyStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'contact_name', 'email', 'phone', 'billing_target', 'status'])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'billing_target' => AgencyBillingTarget::class,
            'status' => AgencyStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot(['notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
