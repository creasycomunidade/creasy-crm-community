<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CA bundle for outbound HTTPS (Guzzle / Laravel Http)
    |--------------------------------------------------------------------------
    |
    | Fixes cURL error 77 when php.ini curl.cainfo / openssl.cafile points to
    | a path that does not exist (common after moving Laragon or PHP installs).
    | Download a current bundle: https://curl.se/ca/cacert.pem
    | Laragon often ships one under: bin/php/php-VERSION/extras/ssl/cacert.pem
    |
    */
    'ca_path' => env('HTTP_CLIENT_CA_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Verify SSL peer certificates
    |--------------------------------------------------------------------------
    |
    | Set to false only on trusted local/testing machines when fixing CA paths
    | is not possible. Production always verifies certificates regardless of
    | this value.
    |
    */
    'verify_ssl' => filter_var(env('HTTP_CLIENT_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

];
