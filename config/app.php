<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'CREASY CRM'),

    'developer' => 'CREASY Tecnologias e Soluções',

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the app runs as a public read-only showcase: create/update/
    | delete operations are blocked across the admin, client and /api/v1 areas,
    | and all contact PII (name, phone, email, address) is masked before it is
    | serialized to the browser. Set APP_DEMO_MODE=true in .env for demos.
    |
    */

    'demo_mode' => (bool) env('APP_DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Installed Flag
    |--------------------------------------------------------------------------
    |
    | False on a fresh deploy. The EnsureInstalled middleware redirects every
    | request to the web setup wizard at /install until installation completes.
    | The wizard writes APP_INSTALLED=true to the .env file as its final step,
    | which permanently locks the installer.
    |
    */

    'installed' => (bool) env('APP_INSTALLED', false),
    'install_token' => env('INSTALL_TOKEN'),

    // Credentials offered by the one-click demo sign-in cards on the login screen
    // while demo mode is active. The client pair mirrors the seeded demo client
    // (database/seeders/UserSeeder.php) and the admin pair mirrors the seeded
    // super admin (database/seeders/AdminUserSeeder.php) so a visitor lands in a
    // populated account. When running a public demo, set CLIENT_SEED_* and
    // ADMIN_SEED_* so the seeded accounts and these buttons share the same login.
    'demo_email' => env('CLIENT_SEED_EMAIL', 'client@example.com'),
    'demo_password' => env('CLIENT_SEED_PASSWORD', 'demo-password'),
    'demo_admin_email' => env('ADMIN_SEED_EMAIL', 'admin@example.com'),
    'demo_admin_password' => env('ADMIN_SEED_PASSWORD', 'demo-password'),

    /*
    |--------------------------------------------------------------------------
    | Health Check Token
    |--------------------------------------------------------------------------
    |
    | Bearer token protecting /healthz/* endpoints. The installer generates a
    | random value. If it is empty, the endpoints fail closed with HTTP 401.
    |
    */

    'healthz_token' => env('HEALTHZ_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'pt'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'pt'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'pt_BR'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
