<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Data;

use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartSummaryData;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\ShippingOptionData;

/**
 * What checkout shows and PlaceOrder charges (roadmap B9.7, S23, S34), worked out on the fly from the cart now: items,
 * the coupon (or why it does not apply), the shipping estimate, what shipping costs, the total, and the address the
 * order goes to. Rial.
 *
 * Shipping is one estimated price (owner, 2026-11-18): the cheapest Tipax service for the packed cart, or the flat
 * rate when Tipax cannot price it; staff pick the service at the branch.
 */
final readonly class CheckoutQuoteData
{
    /**
     * @param  ShippingOptionData  $shippingOption  the estimate charged for shipping
     * @param  list<PackedParcel>  $parcels  the parcels it was priced from (shown outside production only)
     * @param  int  $shippingDiscount  what free shipping takes off it
     * @param  int  $shippingPrice  what shipping costs: the estimate less the discount
     */
    public function __construct(
        public int $cartId,
        public CartSummaryData $cart,
        public ?AppliedCouponData $coupon,
        public ?string $couponProblem,
        public ShippingOptionData $shippingOption,
        public array $parcels,
        public int $shippingDiscount,
        public int $shippingPrice,
        public bool $freeShipping,
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
