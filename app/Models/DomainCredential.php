<?php

namespace App\Models;

use App\Enums\DomainCredentialKind;
use Carbon\CarbonImmutable;
use Database\Factories\DomainCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un acceso técnico de este dominio: la cuenta del registrador (Namecheap,
 * GoDaddy...) o algo del sitio que vive en él —panel de hosting, base de
 * datos, FTP, gestor de contenido—.
 *
 * Es una fila por acceso en vez de columnas fijas porque no todos los sitios
 * tienen WordPress ni FTP, y alguno tiene dos bases de datos.
 *
 * Distinto de `License`: aquí no hay costo ni fecha de renovación porque un
 * acceso no es un producto que se paga, es solo la llave para entrar. Si lo
 * que quieres registrar es algo que el cliente paga y caduca —Brevo,
 * Elementor, WhatsApp Business—, esa es una `License`, no un acceso.
 *
 * @property int $id
 * @property int $domain_id
 * @property DomainCredentialKind $kind
 * @property string|null $label
 * @property string|null $url
 * @property string|null $username
 * @property string|null $password
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['domain_id', 'kind', 'label', 'url', 'username', 'password', 'notes'])]
class DomainCredential extends Model
{
    /** @use HasFactory<DomainCredentialFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => DomainCredentialKind::class,
            'password' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
