<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS (roadmap D11.6)
|--------------------------------------------------------------------------
|
| SMS go through the provider named here (App\Modules\Notifications\Enums\SmsProvider): «melipayamak» or «kavenegar»;
| each provider has its own block with credentials and template key => pattern id (Kavenegar: template name). With «pretend» on (the default, for local and testing)
| nothing is sent: the message goes to the application log and sms_logs marks it as a pretend send.
|
*/

return [
    'provider' => env('SMS_PROVIDER', 'melipayamak'),

    'pretend' => (bool) env('SMS_PRETEND', true),

    'providers' => [
        'melipayamak' => [
            'username' => env('MELIPAYAMAK_USERNAME'),
            // The panel's API key, sent as "password" (the plain password is refused with −110).
            'api_key' => env('MELIPAYAMAK_API_KEY'),
            'base_url' => env('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com'),
            // SendByBaseNumber (pattern SMS on the shared service line).
            'send_pattern_path' => env('MELIPAYAMAK_SEND_PATTERN_PATH', '/api/SendSMS/BaseServiceNumber'),
            // Seconds to open the connection, then for the whole request. A failed connection or a 5xx answer is
            // tried again «retries» times in all, «retry_sleep_ms» apart; SendSmsJob then retries with its backoff.
            'connect_timeout_seconds' => (int) env('MELIPAYAMAK_CONNECT_TIMEOUT_SECONDS', 3),
            'timeout_seconds' => (int) env('MELIPAYAMAK_TIMEOUT_SECONDS', 8),
            'retries' => (int) env('MELIPAYAMAK_RETRIES', 3),
            'retry_sleep_ms' => (int) env('MELIPAYAMAK_RETRY_SLEEP_MS', 300),

            // Template key => pattern bodyId approved in the Melli Payamak panel. S37 adds the order templates.
            'patterns' => [
                'otp' => env('MELIPAYAMAK_PATTERN_OTP'),
                'order_paid' => env('MELIPAYAMAK_PATTERN_ORDER_PAID'),
                'order_on_the_way' => env('MELIPAYAMAK_PATTERN_ORDER_SHIPPED'),
            ],
        ],

        // Kavenegar through its package (kavenegar/php; owner 2026-11-18): template SMS with verify/lookup.
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            // The sender line, for plain messages (template SMS go out on Kavenegar's own service lines).
            'sender' => env('KAVENEGAR_SENDER'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com'),

            // Template key => template name approved in the Kavenegar panel; its %token%, %token2%, %token3% take the
            // message's variables in order.
            'patterns' => [
                'otp' => env('KAVENEGAR_TEMPLATE_OTP'),
                'order_paid' => env('KAVENEGAR_TEMPLATE_ORDER_PAID'),
                'order_on_the_way' => env('KAVENEGAR_TEMPLATE_ORDER_ON_THE_WAY'),
            ],
        ],
    ],
];
