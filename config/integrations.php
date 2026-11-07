<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Integration Log
    |--------------------------------------------------------------------------
    |
    | Every call to an external service (Tipax, Zarinpal, Melli Payamak, …)
    | made through Http::integration() is stored in integration_logs
    | (roadmap D0.4, D3.6). Secrets are replaced before anything is stored
    | (D16); the request is also encrypted at rest (D12.1).
    |
    */

    'log' => [
        // Keys compared case-insensitively, ignoring "_" and "-": "api_key" matches "apiKey" and "API-KEY".
        'redact_keys' => [
            'password', 'passwd', 'secret', 'client_secret',
            'api_key', 'apikey', 'key',
            'token', 'access_token', 'refresh_token', 'id_token',
            'authorization', 'cookie', 'set_cookie',
            'merchant_id', 'merchantid',
            'card', 'card_pan', 'cardpan', 'pan', 'cvv2', 'cvv',
            'x_webhook_secret', 'x_api_key',
        ],
        'redacted' => '[REDACTED]',
        'max_body_bytes' => 65_536,
        'retention_days' => (int) env('INTEGRATION_LOG_RETENTION_DAYS', 90),
        'queue' => env('INTEGRATION_LOG_QUEUE', 'default'),
    ],

];
