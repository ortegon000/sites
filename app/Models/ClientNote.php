<?php

namespace App\Models;

use App\Enums\ClientNoteType;
use Carbon\CarbonImmutable;
use Database\Factories\ClientNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $user_id
 * @property ClientNoteType $type
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'user_id', 'type', 'body'])]
class ClientNote extends Model
{
    /** @use HasFactory<ClientNoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ClientNoteType::class,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
