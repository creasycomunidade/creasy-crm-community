<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Keys
    |--------------------------------------------------------------------------
    | Generate with: php artisan webpush:vapid
    | Or use: openssl ecparam -name prime256v1 -genkey -noout -out vapid_private.pem
    */
    'vapid_public_key' => env('VAPID_PUBLIC_KEY', ''),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY', ''),
];
