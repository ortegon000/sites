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
 * Un acceso técnico del sitio que vive en este dominio: panel de hosting, base
 * de datos, FTP o gestor de contenido.
 *
 * Es una fila por acceso en vez de columnas fijas porque no todos los sitios
 * tienen WordPress ni FTP, y alguno tiene dos bases de datos.
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
