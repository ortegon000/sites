<?php

namespace App\Console\Commands;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\DomainCredentialKind;
use App\Enums\DomainEmailManagement;
use App\Enums\DomainManagement;
use App\Enums\DomainStatus;
use App\Enums\EmailAccountOrigin;
use App\Enums\EmailAccountStatus;
use App\Exceptions\DryRunComplete;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\EmailProvider;
use App\Services\Import\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa el libro de hosting que la agencia llevaba en Excel: la hoja
 * `Cuentas` con los dominios y sus renovaciones, `Emails` con los buzones y
 * `Sitios` con los accesos de servidor.
 *
 * Es idempotente: correrlo dos veces no duplica nada, así que se puede correr
 * en seco, revisar el resumen, y volver a correrlo de verdad.
 */
class ImportHostingWorkbook extends Command
{
    protected $signature = 'import:hosting
        {file : Ruta del .xlsx}
        {--provider= : Nombre del proveedor de correo al que se asignan los buzones}
        {--dry-run : Solo reporta lo que haría}';

    protected $description = 'Importa dominios, buzones y accesos desde el libro de hosting en Excel.';

    private bool $dryRun = false;

    /** @var array<string, int> */
    private array $counters = [
        'clientes' => 0,
        'contactos' => 0,
        'dominios' => 0,
        'buzones' => 0,
        'accesos' => 0,
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("No encuentro el archivo [{$file}].");

            return self::FAILURE;
        }

        $this->dryRun = (bool) $this->option('dry-run');

        $provider = $this->resolveProvider();

        if ($provider === null) {
            return self::FAILURE;
        }

        $reader = new XlsxReader($file);

        $this->info('Hojas encontradas: '.implode(', ', $reader->sheetNames()));

        /**
         * La corrida en seco recorre exactamente el mismo camino y al final
         * deshace la transacción. Simularla con ramas aparte daba conteos
         * falsos, porque sin escribir nada la deduplicación nunca ocurría y
         * cada fila parecía un cliente nuevo.
         */
        try {
            DB::transaction(function () use ($reader, $provider): void {
                $this->importAccounts($reader);
                $this->importMailboxes($reader, $provider);
                $this->importSites($reader);

                if ($this->dryRun) {
                    throw new DryRunComplete;
                }
            });
        } catch (DryRunComplete) {
            // La transacción ya se deshizo; los contadores son los reales.
        }

        $this->newLine();
        $this->table(['Concepto', 'Cantidad'], collect($this->counters)->map(
            fn (int $count, string $label) => [$label, $count],
        )->values()->all());

        if ($this->dryRun) {
            $this->warn('Corrida en seco: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function resolveProvider(): ?EmailProvider
    {
        $name = $this->option('provider');

        if ($name) {
            $provider = EmailProvider::where('name', $name)->first();

            if ($provider === null) {
                $this->error("No encuentro el proveedor [{$name}].");
            }

            return $provider;
        }

        /**
         * Los buzones del libro traen contraseña y no hay API detrás, así que
         * por defecto van a un proveedor manual: es el único que las conserva.
         */
        $provider = EmailProvider::all()->first(fn (EmailProvider $candidate) => $candidate->storesPasswordLocally())
            ?? EmailProvider::orderBy('id')->first();

        if ($provider === null) {
            $this->error('No hay proveedores de correo. Crea uno antes de importar los buzones.');

            return null;
        }

        $this->line("Proveedor de los buzones: {$provider->name}");

        if (! $provider->storesPasswordLocally()) {
            $this->warn('Ese proveedor no guarda contraseñas, así que las del libro se descartarán.');
        }

        return $provider;
    }

    /**
     * La hoja `Cuentas`: un dominio por fila, con su contacto y su renovación.
     */
    private function importAccounts(XlsxReader $reader): void
    {
        foreach ($reader->rows('Cuentas') as $row) {
            $name = strtolower($row['Dominio'] ?? '');

            if ($name === '') {
                continue;
            }

            $client = $this->clientFor($name, $row['Nombre de contacto'] ?? '');

            $domain = $this->domainFor($client, $name, [
                'hosting_plan' => ($row['Plan en Vps'] ?? '') ?: null,
                'hosted_since' => XlsxReader::date($row['Fecha de alta en VPS'] ?? null)?->toDateString(),
                'registered_at' => XlsxReader::date($row['Fecha de alta de dominio'] ?? null)?->toDateString(),
                'expires_at' => XlsxReader::date($row['Fecha Renovacion'] ?? null)?->toDateString(),
                'status' => ($row['Estatus'] ?? '') === 'Activo' ? DomainStatus::Activo : DomainStatus::Expirado,
                'email_notes' => ($row['Notas adicionales'] ?? '') ?: null,
            ]);

            $this->contactFor($client, $row['Nombre de contacto'] ?? '', $row['Correo de contacto'] ?? '', $row['Teléfono de contácto'] ?? '');
        }
    }

    /**
     * La hoja `Emails`: un buzón por fila. El dominio se toma de su columna, y
     * si no estaba en `Cuentas` se crea, porque un buzón sin dominio no existe.
     */
    private function importMailboxes(XlsxReader $reader, EmailProvider $provider): void
    {
        foreach ($reader->rows('Emails') as $row) {
            $address = strtolower($row['email'] ?? '');
            $domainName = strtolower($row['dominio'] ?? '');

            if ($address === '' || $domainName === '' || ! str_contains($address, '@')) {
                continue;
            }

            $client = $this->clientFor($domainName, $row['nombre'] ?? '');
            $domain = $this->domainFor($client, $domainName, ['email_management' => DomainEmailManagement::Managed]);

            if ($domain->emailAccounts()->where('email_address', $address)->exists()) {
                continue;
            }

            $this->counters['buzones']++;

            $domain->emailAccounts()->create([
                'email_provider_id' => $provider->id,
                'email_address' => $address,
                'password' => $provider->storesPasswordLocally() ? (($row['pass'] ?? '') ?: null) : null,
                'origin' => EmailAccountOrigin::Imported,
                'status' => EmailAccountStatus::Activa,
                'provisioned_at' => null,
            ]);
        }
    }

    /**
     * La hoja `Sitios`: una fila por sitio, con hasta cuatro juegos de
     * credenciales que aquí se vuelven una fila cada uno.
     */
    private function importSites(XlsxReader $reader): void
    {
        foreach ($reader->rows('Sitios') as $row) {
            $host = strtolower((string) parse_url($row['site'] ?? '', PHP_URL_HOST));

            if ($host === '') {
                continue;
            }

            $client = $this->clientFor($host, '');
            $domain = $this->domainFor($client, $host, [
                'site_url' => ($row['site'] ?? '') ?: null,
                'status' => ($row['status'] ?? '') === 'Activo' ? DomainStatus::Activo : DomainStatus::Expirado,
            ]);

            $credentials = [
                [DomainCredentialKind::Panel, null, $row['cpanel'] ?? '', $row['user'] ?? '', $row['pass'] ?? ''],
                [DomainCredentialKind::Database, $row['db_name'] ?? '', '', $row['db_user'] ?? '', $row['db_pass'] ?? ''],
                [DomainCredentialKind::Ftp, null, $row['ftp'] ?? '', $row['ftp_user'] ?? '', $row['ftp_pass'] ?? ''],
                [DomainCredentialKind::Cms, 'WordPress', '', $row['wp user'] ?? '', $row['wp pass'] ?? ''],
            ];

            foreach ($credentials as [$kind, $label, $url, $username, $password]) {
                if ($username === '' && $password === '' && $url === '') {
                    continue;
                }

                if ($domain->credentials()->where('kind', $kind)->where('username', $username ?: null)->exists()) {
                    continue;
                }

                $this->counters['accesos']++;

                $domain->credentials()->create([
                    'kind' => $kind,
                    'label' => $label ?: null,
                    'url' => $url ?: null,
                    'username' => $username ?: null,
                    'password' => $password ?: null,
                ]);
            }
        }
    }

    /**
     * El libro no trae el nombre de la empresa, así que se deriva del dominio
     * ("momat.com.mx" → "Momat") y se reutiliza si ya existe. Es una conjetura
     * deliberada: es más rápido renombrar unos cuantos clientes después que
     * capturar quince a mano antes.
     */
    private function clientFor(string $domainName, string $contactName): Client
    {
        $label = Str::of($domainName)->before('.')->replace(['-', '_'], ' ')->title()->toString();

        $client = Client::where('name', $label)->first();

        if ($client !== null) {
            return $client;
        }

        $this->counters['clientes']++;

        return Client::create([
            'type' => ClientType::Client,
            'status' => ClientStatus::Activo,
            'name' => $label,
            'currency' => 'MXN',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function domainFor(Client $client, string $name, array $attributes): Domain
    {
        $domain = $client->domains()->where('name', $name)->first();

        if ($domain !== null) {
            $domain->update(array_filter($attributes, fn ($value) => $value !== null));

            return $domain;
        }

        $this->counters['dominios']++;

        return $client->domains()->create(array_merge([
            'name' => $name,
            'management' => DomainManagement::Managed,
            'auto_renew' => false,
            'email_management' => DomainEmailManagement::NotManaged,
            'status' => DomainStatus::Activo,
        ], array_filter($attributes, fn ($value) => $value !== null)));
    }

    private function contactFor(Client $client, string $name, string $email, string $phone): void
    {
        if ($name === '') {
            return;
        }

        $existing = $email !== ''
            ? Contact::firstWhere('email', $email)
            : Contact::whereNull('email')->firstWhere('name', $name);

        if ($existing !== null) {
            $client->contacts()->syncWithoutDetaching([$existing->id => ['is_primary' => true]]);

            return;
        }

        $this->counters['contactos']++;

        $contact = Contact::create([
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
        ]);

        $client->contacts()->syncWithoutDetaching([$contact->id => ['is_primary' => true]]);
    }
}
