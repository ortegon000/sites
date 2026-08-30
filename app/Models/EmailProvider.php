<?php

namespace App\Models;

use App\Enums\EmailProviderDriverType;
use App\Enums\EmailProviderStatus;
use App\Services\EmailProvisioning\Contracts\EmailProviderDriver;
use App\Services\EmailProvisioning\Drivers\NullEmailProviderDriver;
use Carbon\CarbonImmutable;
use Database\Factories\EmailProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * @property int $id
 * @property string $name
 * @property EmailProviderDriverType $driver
 * @property array<string, mixed>|null $credentials
 * @property EmailProviderStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'driver', 'credentials', 'status'])]
class EmailProvider extends Model
{
    /** @use HasFactory<EmailProviderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'driver' => EmailProviderDriverType::class,
            'credentials' => 'encrypted:array',
            'status' => EmailProviderStatus::class,
        ];
    }

    /**
     * @return HasMany<EmailAccount, $this>
     */
    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function driver(): EmailProviderDriver
    {
        return match ($this->driver) {
            EmailProviderDriverType::NullDriver => app(NullEmailProviderDriver::class),
            default => throw new RuntimeException("El driver [{$this->driver->value}] todavía no está implementado."),
        };
    }
}
