<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

/**
 * The customer presses «پرداخت» (roadmap D13.4, S23): who, the coupon code they applied (if any), the total the page
 * showed them (rial), and an optional note.
 */
final readonly class PlaceOrderData
{
    public function __construct(
        public int $customerId,
        public ?string $couponCode,
        public int $shownTotal,
        public ?string $customerNote = null,
    ) {}
}
