<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

/**
 * A customer's cart as checkout reads it (roadmap S23; the Checkout module since 2026-11-18): its id, the address
 * picked on the address step (if any) and its lines and totals now.
 */
final readonly class CheckoutCartData
{
    public function __construct(
        public int $cartId,
        public ?int $pickedAddressId,
        public CartSummaryData $summary,
    ) {}
}
