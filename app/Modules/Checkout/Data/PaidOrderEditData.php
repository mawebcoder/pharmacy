<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

/**
 * A paid order staff edit before it goes out (owner, 2026-11-19): SKUs and units (a SKU left out is removed) and one
 * of the customer's saved addresses.
 */
final readonly class PaidOrderEditData
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function __construct(
        public int $orderId,
        public int $addressId,
        public array $quantities,
        public int $adminId,
    ) {}
}
