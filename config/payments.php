<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Payments (roadmap D7, S23, S36)
|--------------------------------------------------------------------------
|
| «gateway» picks the PaymentGateway: «fake» (local and staging, until Zarinpal in S36) offers pay / fail buttons and
| works only while «fake_enabled» is on, so production can never take fake payments. An order waits for its payment
| «pending_expiry_minutes»; its payment link and coupon reservation last as long.
|
*/

return [
    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'fake_enabled' => (bool) env('PAYMENT_FAKE_ENABLED', false),

    'pending_expiry_minutes' => (int) env('PAYMENT_PENDING_EXPIRY_MINUTES', 30),
];
