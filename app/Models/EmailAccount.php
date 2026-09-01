<?php

namespace App\Models;

use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EmailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $domain_id
 * @property int $email_provider_id
 * @property string $email_address
 * @property string|null $password
 * @property EmailAccountOrigin $origin
 * @property EmailAccountStatus $status
 * @property CarbonImmutable|null $provisioned_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['domain_id', 'email_provider_id', 'email_address', 'password', 'origin', 'status', 'provisioned_at'])]
class EmailAccount extends Model
{
    /** @use HasFactory<EmailAccountFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'origin' => EmailAccountOrigin::class,
            'status' => EmailAccountStatus::class,
            'provisioned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<EmailProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'email_provider_id');
    }
}
