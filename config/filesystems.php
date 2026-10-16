<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => env('PUBLIC_STORAGE_DIRECT', false) ? public_path('storage') : storage_path('app/public'),
            // rtrim guards against a trailing slash in APP_URL (e.g. "https://site/"),
            // which would otherwise yield "https://site//storage/..." (double slash)
            // and 404 every uploaded logo/favicon/media asset.
            'url' => rtrim(env('APP_URL'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Cloud storage disks — credentials are stored encrypted in the database
        // and injected at runtime by App\Services\StorageManager.
        // Do NOT add credential env vars here; use Admin › Integrations › Storage.
        's3' => [
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => 'us-east-1',
            'bucket' => null,
            'url' => null,
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        'do_spaces' => [
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => 'nyc3',
            'bucket' => null,
            'url' => null,
            'endpoint' => 'https://nyc3.digitaloceanspaces.com',
            'use_path_style_endpoint' => false,
            'throw' => false,
            'visibility' => 'public',
            'options' => ['ACL' => 'public-read'],
        ],

        'wasabi' => [
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => 'us-east-1',
            'bucket' => null,
            'url' => null,
            'endpoint' => 'https://s3.wasabisys.com',
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        // Private off-site backups. Prefer dedicated BACKUP_AWS_* credentials;
        // AWS_* remains a compatibility fallback for existing installations.
        'backup' => [
            'driver' => 's3',
            'key' => env('BACKUP_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('BACKUP_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('BACKUP_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('BACKUP_AWS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('BACKUP_AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('BACKUP_AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'visibility' => 'private',
            'options' => [
                'ServerSideEncryption' => 'AES256',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
