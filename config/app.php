<?php

declare(strict_types=1);

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

    'name' => env('APP_NAME', 'Laravel'),

    /*
    | The shop name people see (header, footer, page titles, admin brand). Kept apart from APP_NAME because
    | APP_NAME also builds Redis and Horizon key prefixes. Moves to site settings in S30.
    */

    'display_name' => env('APP_DISPLAY_NAME', 'هوتانا'),

    /*
    |--------------------------------------------------------------------------
    | Admin panel domain
    |--------------------------------------------------------------------------
    |
    | When set, the admin (Filament) is served at the root of this subdomain, e.g. admin.hutana.ir (owner, 2026-11-17).
    | Empty: it stays at /admin on the store's domain (tests and other setups).
    |
    */

    'admin_domain' => env('ADMIN_DOMAIN') ?: null,

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

    // Production behind the CDN: every plain-HTTP request is redirected to HTTPS and every generated URL uses
    // https:// on APP_URL's domain, whatever host the request came in on. Off by default, so local development and CI's smoke test over http://localhost are unaffected.
    'force_https' => (bool) env('APP_FORCE_HTTPS', false),

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

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    | The system user that runs commands from «فرمان‌ها» in the admin (owner, 2026-11-28): "deploy" on the server,
    | where only the deploy user's worker takes the "commands" queue. Empty: any user (local Docker).
    */
    'commands_user' => env('ADMIN_COMMANDS_USER'),

    /*
    |--------------------------------------------------------------------------
    | Display and Schedule Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC (the timezone above) and converted to this
    | timezone, as Jalali dates, wherever people see them: the storefront via
    | Carbon's toJalali() and the admin panel via FilamentTimezone. Scheduled
    | tasks run on the same local clock (e.g. dailyAt('02:00') is 02:00 Tehran).
    |
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Tehran'),

    'schedule_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Tehran'),

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

    'locale' => env('APP_LOCALE', 'fa'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'fa'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'fa_IR'),

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
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
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
    | Supported drivers: "file", "cache", "array"
    |
    */

    // On Redis by default (owner, 2026-11-28): `php artisan down` reaches every PHP-FPM worker, queue worker and
    // release, since none of them reads it from a file of its own. The page is resources/views/errors/503.blade.php.
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'cache'),
        'store' => env('APP_MAINTENANCE_STORE', 'redis'),
    ],

];
