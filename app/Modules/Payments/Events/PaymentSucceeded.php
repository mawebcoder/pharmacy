<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

/**
 * An order's payment was verified (roadmap D13.5): Orders marks it paid and settles its coupon.
 */
final readonly class PaymentSucceeded
{
    public function __construct(
        public int $orderId,
        public int $paymentId,
    ) {}
}
