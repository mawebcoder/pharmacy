<?php

declare(strict_types=1);

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
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Generated image sizes (roadmap D8, D14): public, cached forever (content hash in the path).
        // Served by nginx under /storage/media locally; production uses the "cdn" disk instead.
        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage/media',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // ArvanCloud Object Storage (S3-compatible) behind the ArvanCloud CDN, inside Iran (roadmap B4, D11.1).
        // Needs league/flysystem-aws-s3-v3, installed with the deployment step.
        'cdn' => [
            'driver' => 's3',
            'key' => env('IMAGES_CDN_ACCESS_KEY'),
            'secret' => env('IMAGES_CDN_SECRET_KEY'),
            'region' => env('IMAGES_CDN_REGION', 'default'),
            'bucket' => env('IMAGES_CDN_BUCKET'),
            'endpoint' => env('IMAGES_CDN_ENDPOINT'),
            'url' => env('IMAGES_CDN_URL'),
            'visibility' => 'public',
            'use_path_style_endpoint' => true,
            'options' => [
                'CacheControl' => 'public, max-age=31536000, immutable',
            ],
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
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
