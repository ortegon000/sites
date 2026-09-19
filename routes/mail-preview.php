<?php

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Domain;
use App\Models\License;
use App\Models\Renewal;
use App\Notifications\ChargeDueSoonNotification;
use App\Notifications\ChargeOverdueNotification;
use App\Notifications\DomainExpiringNotification;
use App\Notifications\LicenseRenewalDueNotification;
use App\Notifications\RenewalNoticeNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Route;

/*
 * TEMPORAL: vista previa de los correos, con el primer registro de cada tipo.
 * Solo se carga en local (ver routes/web.php). Se borra junto con su
 * `require` cuando termine el diseño de los correos.
 *
 * Cada ruta devuelve el mensaje de `toMail`, que Laravel muestra como una
 * página normal sin enviar nada.
 */
Route::middleware('auth')->prefix('vista-previa/correos')->name('mail-preview.')->group(function () {
    $previews = [
        'aviso-renovacion' => ['Aviso de renovación', 'Al cliente', fn () => new RenewalNoticeNotification(Renewal::firstOrFail())],
        'cobro-por-vencer' => ['Cobro por vencer', 'Al equipo', fn () => new ChargeDueSoonNotification(Charge::whereIn('status', [ChargeStatus::Pendiente, ChargeStatus::Parcial])->firstOrFail())],
        'cobro-vencido' => ['Cobro vencido', 'Al equipo', fn () => new ChargeOverdueNotification(Charge::where('status', ChargeStatus::Vencido)->firstOrFail())],
        'dominio-por-vencer' => ['Dominio por vencer', 'Al equipo', fn () => new DomainExpiringNotification(Domain::firstOrFail())],
        'licencia-mensual' => ['Licencia mensual por renovar', 'Al equipo', fn () => new LicenseRenewalDueNotification(License::firstOrFail())],
    ];

    Route::get('/', function () use ($previews) {
        $links = collect($previews)->map(fn (array $preview, string $slug): string => sprintf(
            '<li style="margin:.5rem 0"><a href="%s">%s</a> <small style="color:#888">— %s</small></li>',
            e(route('mail-preview.show', $slug)),
            e($preview[0]),
            e($preview[1]),
        ))->implode('');

        return response("<!doctype html><meta charset=\"utf-8\"><title>Vista previa de correos</title><body style=\"font-family:system-ui;margin:2rem\"><h1>Vista previa de correos</h1><ul>{$links}</ul></body>");
    })->name('index');

    Route::get('{slug}', function (string $slug) use ($previews) {
        abort_unless(isset($previews[$slug]), 404);

        return $previews[$slug][2]()->toMail(new AnonymousNotifiable);
    })->name('show');
});
