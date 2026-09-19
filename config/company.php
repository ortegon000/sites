<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de contacto para los clientes
    |--------------------------------------------------------------------------
    |
    | Salen al pie de los correos que van al cliente, para que escriban por
    | cualquier duda. PROVISIONAL: son datos inventados de prueba; cámbialos
    | por los reales desde el .env antes de mandar correos de verdad.
    |
    */

    'contact' => [
        'email' => env('COMPANY_EMAIL', 'hola@sites.test'),
        'whatsapp' => env('COMPANY_WHATSAPP', '5512345678'),
        'phone' => env('COMPANY_PHONE', '5587654321'),
    ],

];
