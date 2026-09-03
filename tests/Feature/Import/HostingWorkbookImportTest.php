<?php

use App\Enums\DomainCredentialKind;
use App\Enums\EmailAccountOrigin;
use App\Enums\EmailProviderDriverType;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainCredential;
use App\Models\EmailAccount;
use App\Models\EmailProvider;

/**
 * Arma un .xlsx mínimo para no depender de un archivo binario en el repo ni de
 * una librería de escritura: un xlsx es un zip de XML, y el lector solo necesita
 * cadenas en línea.
 *
 * @param  array<string, array<int, array<int, string>>>  $sheets
 */
function makeWorkbook(array $sheets): string
{
    $path = tempnam(sys_get_temp_dir(), 'libro').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $sheetXml = [];
    $names = array_keys($sheets);

    foreach (array_values($sheets) as $index => $rows) {
        $body = '';

        foreach ($rows as $rowNumber => $cells) {
            $body .= '<row r="'.($rowNumber + 1).'">';

            foreach ($cells as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).($rowNumber + 1);
                $body .= '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }

            $body .= '</row>';
        }

        $sheetXml[$index] = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$body.'</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $sheetXml[$index]);
    }

    $sheetTags = '';
    $relTags = '';

    foreach ($names as $index => $name) {
        $sheetTags .= '<sheet name="'.$name.'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        $relTags .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
    }

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetTags.'</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relTags.'</Relationships>');
    $zip->close();

    return $path;
}

function hostingWorkbook(): string
{
    return makeWorkbook([
        'Emails' => [
            ['nombre', 'dominio', 'email', 'pass'],
            ['Ana', 'acme.mx', 'ana@acme.mx', 'clave-de-ana'],
            ['Luis', 'acme.mx', 'luis@acme.mx', 'clave-de-luis'],
            ['', '', '', ''],
        ],
        'Sitios' => [
            ['site', 'cpanel', 'status', 'user', 'pass', 'db_name', 'db_user', 'db_pass', 'ftp', 'ftp_user', 'ftp_pass', 'wp user', 'wp pass'],
            ['https://acme.mx', 'https://cpanel.acme.mx', 'Activo', 'acme', 'clave-cpanel', 'acme_wp', 'acme_db', 'clave-db', '', '', '', 'admin', 'clave-wp'],
        ],
        'Cuentas' => [
            ['Dominio', 'Estatus', 'Nombre de contacto', 'Correo de contacto', 'Teléfono de contácto', 'Plan en Vps', 'Fecha de alta en VPS', 'Fecha de alta de dominio', 'Fecha Renovacion'],
            ['acme.mx', 'Activo', 'Ana Gómez', 'ana@acme.mx', '55 1111 2222', 'basic', '43982', '42012', '46000'],
        ],
    ]);
}

beforeEach(function () {
    EmailProvider::factory()->create([
        'name' => 'MXroute (simulado)',
        'driver' => EmailProviderDriverType::NullDriver,
    ]);

    EmailProvider::factory()->manual()->create(['name' => 'Proveedor manual']);
});

test('the workbook lands as client, domain, mailboxes and accesses', function () {
    $this->artisan('import:hosting', ['file' => hostingWorkbook()])->assertSuccessful();

    $client = Client::where('name', 'Acme')->firstOrFail();
    $domain = $client->domains()->where('name', 'acme.mx')->firstOrFail();

    expect($domain->hosting_plan)->toBe('basic')
        ->and($domain->site_url)->toBe('https://acme.mx')
        ->and($domain->hosted_since->toDateString())->toBe('2020-05-31')
        ->and($domain->emailAccounts()->count())->toBe(2)
        ->and($domain->credentials()->count())->toBe(3)
        ->and($client->contacts()->where('email', 'ana@acme.mx')->exists())->toBeTrue();

    $database = $domain->credentials()->where('kind', DomainCredentialKind::Database)->firstOrFail();

    expect($database->label)->toBe('acme_wp')
        ->and($database->username)->toBe('acme_db')
        ->and($database->password)->toBe('clave-db');
});

test('mailbox passwords are kept because the default provider has no API', function () {
    $this->artisan('import:hosting', ['file' => hostingWorkbook()])->assertSuccessful();

    $mailbox = EmailAccount::where('email_address', 'ana@acme.mx')->firstOrFail();

    expect($mailbox->password)->toBe('clave-de-ana')
        ->and($mailbox->origin)->toBe(EmailAccountOrigin::Imported)
        ->and($mailbox->provider->name)->toBe('Proveedor manual');
});

test('an api-backed provider drops the passwords instead of storing them', function () {
    $this->artisan('import:hosting', [
        'file' => hostingWorkbook(),
        '--provider' => 'MXroute (simulado)',
    ])->assertSuccessful();

    expect(EmailAccount::where('email_address', 'ana@acme.mx')->firstOrFail()->password)->toBeNull();
});

test('running it twice changes nothing', function () {
    $file = hostingWorkbook();

    $this->artisan('import:hosting', ['file' => $file])->assertSuccessful();
    $this->artisan('import:hosting', ['file' => $file])->assertSuccessful();

    expect(Client::where('name', 'Acme')->count())->toBe(1)
        ->and(Domain::where('name', 'acme.mx')->count())->toBe(1)
        ->and(EmailAccount::count())->toBe(2)
        ->and(DomainCredential::count())->toBe(3);
});

test('a dry run writes nothing', function () {
    $this->artisan('import:hosting', ['file' => hostingWorkbook(), '--dry-run' => true])->assertSuccessful();

    expect(Client::where('name', 'Acme')->exists())->toBeFalse()
        ->and(Domain::count())->toBe(0)
        ->and(EmailAccount::count())->toBe(0)
        ->and(DomainCredential::count())->toBe(0);
});
