<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

/**
 * A placed order and where to pay it; paymentUrl is null when the gateway could not be reached (the customer pays
 * later from «سفارش‌ها»).
 */
final readonly class PlacedOrderData
{
    public function __construct(
        public int $orderId,
        public string $number,
        public ?string $paymentUrl,
    ) {}
}
