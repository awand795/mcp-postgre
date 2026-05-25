<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi CORS untuk mengizinkan request dari ERP frontend (iframe)
    | ke Chatbot backend. supports_credentials WAJIB true karena kita
    | menggunakan session-based auth (cookie).
    |
    | Catatan: allowed_origins '*' + supports_credentials = true memang
    | bertentangan di spec CORS untuk credentialed AJAX request. Namun
    | untuk flow SSO berbasis iframe redirect (bukan AJAX), ini tidak masalah.
    | Jika nanti dibutuhkan AJAX credentialed dari JS di dalam iframe,
    | ganti '*' dengan origin ERP yang spesifik, contoh:
    |   'allowed_origins' => ['https://erp.perusahaan.com'],
    |
    */

    'paths' => [
        'api/*',
        'auth/sso',
        'chatbot/*',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | WAJIB true agar session cookie dikirim pada cross-origin request
    | (iframe dari domain ERP yang berbeda).
    */
    'supports_credentials' => true,

];
