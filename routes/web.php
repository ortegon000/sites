<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/**
 * El enlace que se le manda al cliente: sin cuenta, protegido solo por lo
 * impredecible del token (40 caracteres al azar), no por sesión.
 */
Route::livewire('c/{quote:public_token}', 'pages::quotes.public')->name('quotes.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/crm.php';
require __DIR__.'/portal.php';
