<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una licencia o suscripción que el cliente tiene y la agencia administra:
 * Brevo, Elementor, un tema de WordPress, una cuenta de WhatsApp Business.
 *
 * Cuelga del cliente y no del dominio porque muchas no son de un sitio en
 * particular —Brevo es del cliente— aunque otras sí, así que el dominio es
 * opcional. Como todo activo, caduca y avisa antes de hacerlo.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $domain_id
 * @property string $name
 * @property string|null $vendor
 * @property string|null $url
 * @property string|null $username
 * @property string|null $password
 * @property string|null $cost
 * @property string $currency
 * @property CarbonImmutable|null $renewal_date
 * @property bool $auto_renew
 * @property LicenseStatus $status
 * @property CarbonImmutable|null $expiry_notified_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'domain_id', 'name', 'vendor', 'url', 'username', 'password', 'cost', 'currency', 'renewal_date', 'auto_renew', 'status', 'expiry_notified_at', 'notes'])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Renovar una licencia empuja `renewal_date`, lo que abre un ciclo nuevo:
     * limpiar la marca es lo que deja que el aviso del año siguiente vuelva a
     * salir, en vez de que la licencia se quede callada tras un solo aviso.
     */
    protected static function booted(): void
    {
        static::updating(function (License $license): void {
            if ($license->isDirty('renewal_date')) {
                $license->expiry_notified_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'password' => 'encrypted',
            'renewal_date' => 'date',
            'auto_renew' => 'boolean',
            'expiry_notified_at' => 'datetime',
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
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
