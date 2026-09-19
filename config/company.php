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

    /*
    |--------------------------------------------------------------------------
    | Cuenta para depositar las renovaciones
    |--------------------------------------------------------------------------
    |
    | Aparece en el aviso de renovación. PROVISIONAL: es una cuenta inventada
    | de prueba, no existe; reemplázala desde el .env por la real antes de
    | mandar correos a clientes, porque el correo les pide depositar ahí.
    |
    */

    'bank' => [
        'bank' => env('COMPANY_BANK_NAME', 'BBVA México'),
        'holder' => env('COMPANY_BANK_HOLDER', 'Sites Servicios Digitales, S.A. de C.V.'),
        'clabe' => env('COMPANY_BANK_CLABE', '012180001234567891'),
        'account' => env('COMPANY_BANK_ACCOUNT', '0123456789'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Avisos de renovación automáticos al cliente
    |--------------------------------------------------------------------------
    |
    | Apagado por ahora: el aviso al cliente sale solo cuando alguien lo manda
    | con "Avisar al cliente" en Renovaciones. Los correos internos al equipo
    | no dependen de esto. Para volver al envío automático diario,
    | RENEWAL_NOTICES_AUTOMATIC=true en el .env.
    |
    */

    'renewal_notices_automatic' => (bool) env('RENEWAL_NOTICES_AUTOMATIC', false),

];
