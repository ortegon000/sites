<?php

namespace App\Models;

use App\Enums\AgencyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property AgencyStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'contact_name', 'email', 'phone', 'status'])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AgencyStatus::class,
        ];
    }

    /**
     * Los proyectos de la agencia son los de sus clientes: un proyecto
     * pertenece a la agencia a la que pertenece su cliente, y a ninguna otra.
     *
     * @return HasManyThrough<Project, Client, $this>
     */
    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, Client::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
