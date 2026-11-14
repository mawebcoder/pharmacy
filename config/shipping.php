<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shipping (roadmap D6, D11.3)
|--------------------------------------------------------------------------
|
| Tipax: the OM API (v4; sign-in is only in v3). TIPAX_BASE_URL is the test API (https://omtestapi.tipax.ir) until go-live. Provinces and
| cities come from Tipax (`php artisan tipax:sync-cities`, run on deploy and weekly; decided 2026-11-14 in S18).
| Credentials live only in .env.
|
*/

return [
    'tipax' => [
        'enabled' => (bool) env('TIPAX_ENABLED', true),
        'base_url' => env('TIPAX_BASE_URL', 'https://omtestapi.tipax.ir'),
        'username' => env('TIPAX_USERNAME'),
        'password' => env('TIPAX_PASSWORD'),
        'api_key' => env('TIPAX_API_KEY'),
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => (int) env('TIPAX_TIMEOUT_SECONDS', 10),
        'retries' => 2,
        'retry_sleep_ms' => 500,
        'paths' => [
            'token' => '/api/OM/v3/Account/token',
            'cities' => '/api/OM/v4/Cities/plusstate',
            'branch_cities' => '/api/OM/v4/Cities/BranchCities',
        ],
    ],
];
