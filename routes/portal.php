<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:client'])->group(function () {
    Route::livewire('portal', 'pages::portal.projects.index')->name('portal.projects.index');
    Route::livewire('portal/proyectos/{project}', 'pages::portal.projects.show')->name('portal.projects.show');
});
