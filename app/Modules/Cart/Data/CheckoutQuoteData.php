<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Identity\Data\AddressSnapshotData;

/**
 * What checkout shows and PlaceOrder charges (roadmap B9.7, S23), worked out on the fly from the cart now: items,
 * the coupon (or why it does not apply), the shipping price, the total, and the address the order goes to. Rial.
 */
final readonly class CheckoutQuoteData
{
    public function __construct(
        public int $cartId,
        public CartSummaryData $cart,
        public ?AppliedCouponData $coupon,
        public ?string $couponProblem,
        public int $shippingPrice,
        public bool $freeShipping,
        public int $flatRate,
        public int $freeShippingThreshold,
        public ?string $freeShippingReason,
        public ?AddressSnapshotData $address,
    ) {}

    public function discount(): int
    {
        return $this->coupon instanceof AppliedCouponData ? $this->coupon->discount : 0;
    }

    public function total(): int
    {
        return max(0, $this->cart->subtotal - $this->discount()) + $this->shippingPrice;
    }

    /**
     * Lines that can no longer be bought, which block paying until removed.
     *
     * @return list<string> product names
     */
    public function unavailableNames(): array
    {
        $names = [];

        foreach ($this->cart->lines as $line) {
            if (! $line->isAvailable()) {
                $names[] = $line->variant->productName;
            }
        }

        return $names;
    }
}
