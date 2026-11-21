<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

/**
 * An order staff place in the panel for a customer (owner, 2026-11-19): one of the customer's saved addresses, SKUs
 * and quantities at the site's prices, an optional discount (rial) with its reason, and who placed it.
 */
final readonly class PanelOrderData
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function __construct(
        public int $customerId,
        public int $addressId,
        public array $quantities,
        public int $manualDiscount,
        public ?string $manualDiscountReason,
        public ?string $customerNote,
        public int $adminId,
    ) {}
}
