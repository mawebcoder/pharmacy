<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

/**
 * An unpaid order being edited (owner, 2026-11-19): by its customer on their order page, or by staff for a panel order
 * (then also its discount). Quantities are SKUs and units; a SKU left out is removed.
 */
final readonly class ReviseOrderData
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function __construct(
        public int $orderId,
        public int $addressId,
        public array $quantities,
        public ?int $customerId = null,
        public ?int $adminId = null,
        public ?int $manualDiscount = null,
        public ?string $manualDiscountReason = null,
    ) {}
}
