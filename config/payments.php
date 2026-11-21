<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Payments (roadmap D7, S23, S36)
|--------------------------------------------------------------------------
|
| «gateway» picks the PaymentGateway: «zarinpal» (S36), or «fake» (local and staging) which offers pay / fail buttons
| and works only while «fake_enabled» is on, so production can never take fake payments. An order waits for its
| payment «pending_expiry_minutes»; its payment link and coupon reservation last as long.
|
| Zarinpal (D7, owner 2026-11-18): the sandbox locally (any callback domain, no real money), the real gateway on the
| hutana.ir server, whose callback domain is the one registered with Zarinpal (else error −14). The merchant id and
| every address live only in .env.
|
*/

return [
    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'fake_enabled' => (bool) env('PAYMENT_FAKE_ENABLED', false),

    // Between 15 and 30 minutes (owner, 2026-11-19), for site orders and orders staff place in the panel alike.
    'pending_expiry_minutes' => min(30, max(15, (int) env('PAYMENT_PENDING_EXPIRY_MINUTES', 30))),

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => (bool) env('ZARINPAL_SANDBOX', true),
        'base_url' => env('ZARINPAL_BASE_URL', 'https://payment.zarinpal.com'),
        'sandbox_base_url' => env('ZARINPAL_SANDBOX_BASE_URL', 'https://sandbox.zarinpal.com'),
        // Where Zarinpal sends the customer back; null means this site's «payments.callback» route.
        'callback_url' => env('ZARINPAL_CALLBACK_URL'),
        // Rial, always sent explicitly (D7.2).
        'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
        'timeout_seconds' => (int) env('ZARINPAL_TIMEOUT_SECONDS', 15),
        // Pending payments Zarinpal took money for but nobody verified (the customer closed the browser), asked for
        // every this many minutes.
        'reconcile_every_minutes' => 10,
    ],
];
