<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario administrador inicial
    |--------------------------------------------------------------------------
    |
    | Datos que usa AdminSeeder para crear la cuenta de administrador real (la
    | del dueño de la agencia, distinta de los usuarios de demostración que
    | siembra UserSeeder). La contraseña vive en el entorno y nunca en el
    | repositorio: sin ella el seeder no hace nada.
    |
    */

    'admin' => [
        'name' => env('ADMIN_SEED_NAME', 'Ortega'),
        'email' => env('ADMIN_SEED_EMAIL', 'ortegon000@gmail.com'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

];
