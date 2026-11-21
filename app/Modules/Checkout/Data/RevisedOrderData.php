<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

/**
 * An edited unpaid order: its new total, and why its coupon was dropped when it no longer applies.
 */
final readonly class RevisedOrderData
{
    public function __construct(
        public int $total,
        public ?string $couponDropped,
    ) {}
}
