<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

/**
 * The customer paid the difference owed after staff edited a paid order (owner, 2026-11-19).
 */
final readonly class DifferencePaymentSucceeded
{
    public function __construct(
        public int $orderId,
        public int $paymentId,
        public int $amount,
    ) {}
}
