<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Payments (roadmap D7, S26)
|--------------------------------------------------------------------------
|
| How long an order waits for its payment at the gateway before it expires. Coupon reservations made when the
| payment link is created (S20) last as long. S26 adds the Zarinpal gateway settings here.
|
*/

return [
    'pending_expiry_minutes' => (int) env('PAYMENT_PENDING_EXPIRY_MINUTES', 30),
];
