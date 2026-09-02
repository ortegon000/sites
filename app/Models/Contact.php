<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una persona, escrita una sola vez. Puede ser el contacto de varias empresas
 * —un dueño con tres negocios es un contacto y tres clientes— y de ahí sale
 * la vista consolidada sin necesidad de una entidad "grupo" aparte.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'notes'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps()
            ->orderByDesc('client_contact.is_primary')
            ->orderBy('clients.name');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
