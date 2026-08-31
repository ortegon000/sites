<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/crm.php';
require __DIR__.'/portal.php';
