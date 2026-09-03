<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:client'])->group(function () {
    Route::livewire('portal', 'pages::portal.services.index')->name('portal.services.index');
    Route::livewire('portal/cobros', 'pages::portal.charges.index')->name('portal.charges.index');
    Route::livewire('portal/proyectos/{project}', 'pages::portal.projects.show')->name('portal.projects.show');
    Route::livewire('portal/correo', 'pages::portal.email-accounts.index')->name('portal.email-accounts.index');
    Route::livewire('portal/renovaciones', 'pages::portal.renewals.index')->name('portal.renewals.index');
});
