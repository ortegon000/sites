<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        if (auth()->user()->isClient()) {
            return redirect()->route('portal.projects.index');
        }

        return view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/crm.php';
require __DIR__.'/portal.php';
