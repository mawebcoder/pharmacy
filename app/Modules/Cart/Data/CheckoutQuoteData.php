<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Shipping\Data\ShippingOptionData;

/**
 * What checkout shows and PlaceOrder charges (roadmap B9.7, S23, S34), worked out on the fly from the cart now: items,
 * the coupon (or why it does not apply), the shipping options and the one picked, what shipping costs, the total, and
 * the address the order goes to. Rial.
 *
 * Free shipping (over the threshold or by coupon) covers the cheapest option; a dearer one costs the difference
 * (S34, 2026-11-18).
 */
final readonly class CheckoutQuoteData
{
    /**
     * @param  list<ShippingOptionData>  $shippingOptions  cheapest first, never empty
     * @param  int  $shippingDiscount  what free shipping takes off the picked option's price
     * @param  int  $shippingPrice  what shipping costs: the picked option's price less the discount
     */
    public function __construct(
        public int $cartId,
        public CartSummaryData $cart,
        public ?AppliedCouponData $coupon,
        public ?string $couponProblem,
        public array $shippingOptions,
        public ShippingOptionData $shippingOption,
        public int $shippingDiscount,
        public int $shippingPrice,
        public bool $freeShipping,
        public int $freeShippingThreshold,
        public ?string $freeShippingReason,
        public ?AddressSnapshotData $address,
    ) {}

    /**
     * What shipping would cost with this option, after free shipping.
     */
    public function shippingCostOf(ShippingOptionData $option): int
    {
        $discount = $this->freeShipping ? min($option->price, $this->shippingOptions[0]->price) : 0;

        return $option->price - $discount;
    }

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
