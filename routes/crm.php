<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin,staff'])->group(function () {
    Route::livewire('clientes', 'pages::clients.index')->name('clients.index');
    Route::livewire('prospectos', 'pages::clients.index')->name('prospects.index');
    Route::livewire('clientes/{client}', 'pages::clients.show')->name('clients.show');
});
