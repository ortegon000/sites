<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin,staff'])->group(function () {
    Route::livewire('clientes', 'pages::clients.index')->name('clients.index');
    Route::livewire('prospectos', 'pages::clients.index')->name('prospects.index');
    Route::livewire('clientes/contactos', 'pages::contacts.index')->name('contacts.index');
    Route::livewire('clientes/contactos/{contact}', 'pages::contacts.show')->name('contacts.show');
    Route::livewire('clientes/{client}', 'pages::clients.show')->name('clients.show');
    Route::livewire('prospectos/{client}', 'pages::clients.show')->name('prospects.show');
    Route::livewire('agencias', 'pages::agencies.index')->name('agencies.index');
});

Route::middleware(['auth', 'verified', 'role:admin,staff,collaborator'])->group(function () {
    Route::livewire('proyectos', 'pages::projects.index')->name('projects.index');
    Route::livewire('proyectos/{project}', 'pages::projects.show')->name('projects.show');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::livewire('proveedores-correo', 'pages::email-providers.index')->name('email-providers.index');
});
