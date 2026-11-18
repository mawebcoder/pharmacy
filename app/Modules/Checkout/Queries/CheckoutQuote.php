<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Queries;

use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Queries\CheckoutCart;
use App\Modules\Checkout\Data\CheckoutQuoteData;
use App\Modules\Catalog\Queries\OrderItemSnapshots;
use App\Modules\Identity\Queries\CustomerAddresses;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Queries\ShippingOptions;

/**
 * Works out checkout from the customer's cart as it is now (roadmap S23, S34): current prices and stock, the coupon
 * checked on the fly (nothing is written), the picked address or else the default, and one estimated shipping price
 * for it: the cart packed into our boxes and priced by the shipping services (Tipax), cheapest first, the flat rate
 * when none answers; free over the threshold or with a free-shipping coupon. PlaceOrder uses the same quote, so what
 * is charged is what was shown.
 */
final readonly class CheckoutQuote
{
    public function __construct(
        private CheckoutCart $carts,
        private StoreSettings $settings,
        private CustomerAddresses $addresses,
        private ShippingOptions $shippingOptions,
        private OrderItemSnapshots $itemSnapshots,
    ) {}

    /**
     * The customer's checkout now, or null when they have no cart.
     */
    public function forCustomer(int $customerId, ?string $couponCode): ?CheckoutQuoteData
    {
        $cart = $this->carts->forCustomer($customerId);

        if ($cart === null) {
            return null;
        }

        $summary = $cart->summary;
        $coupon = null;
        $problem = null;

        if ($couponCode !== null && $couponCode !== '') {
            try {
                $coupon = $this->carts->coupon($couponCode, $summary->lines, $customerId);
            } catch (CouponRefused $refused) {
                $problem = $refused->getMessage();
            }
        }

        $threshold = $this->settings->freeShippingThreshold();
        $freeByThreshold = $threshold > 0 && $summary->subtotal >= $threshold;
        $freeByCoupon = $coupon instanceof AppliedCouponData && $coupon->freeShipping;
        $free = $freeByThreshold || $freeByCoupon;
        $addressId = $this->addresses->chosenId($customerId, $cart->pickedAddressId);
        $address = $addressId === null ? null : $this->addresses->snapshot($customerId, $addressId);
        // One price (owner, 2026-11-18): the cheapest option for the packed cart.
        $shipping = $this->shippingOptions->for($address?->cityId, $this->packItems($summary->lines));
        $estimate = $shipping->cheapest();
        $discount = $free ? $estimate->price : 0;

        return new CheckoutQuoteData(
            cartId: $cart->cartId,
            cart: $summary->withCoupon($coupon),
            coupon: $coupon,
            couponProblem: $problem,
            shippingOption: $estimate,
            parcels: $shipping->parcels,
            shippingDiscount: $discount,
            shippingPrice: $estimate->price - $discount,
            freeShipping: $free,
            freeShippingThreshold: $threshold,
            freeShippingReason: $freeByCoupon ? 'coupon' : ($freeByThreshold ? 'threshold' : null),
            address: $address,
        );
    }

    /**
     * The cart's lines as Shipping packs them (S34): each SKU's weight, how many fit each box, «ارسال جداگانه» and
     * liquid or fragile, from the same snapshot an order item keeps, so checkout packs as the paid order will.
     *
     * @param  list<CartLineData>  $lines
     * @return list<PackItem>
     */
    private function packItems(array $lines): array
    {
        $snapshots = $this->itemSnapshots->for(array_map(fn (CartLineData $line): int => $line->variant->variantId, $lines));
        $items = [];

        foreach ($lines as $index => $line) {
            if (! $line->isAvailable()) {
                continue;
            }

            /** @var array<string, mixed> $sku */
            $sku = $snapshots[$line->variant->variantId]['sku'] ?? [];
            $weight = $sku['weight_g'] ?? null;
            $capacities = [];

            foreach ((array) ($sku['box_capacities'] ?? []) as $boxId => $count) {
                if ((int) $count > 0) {
                    $capacities[(int) $boxId] = (int) $count;
                }
            }

            $items[] = new PackItem(
                key: $index,
                variantId: $line->variant->variantId,
                title: $line->variant->productName,
                quantity: $line->quantity,
                unitWeightG: is_numeric($weight) && (int) $weight > 0 ? (int) $weight : null,
                boxCapacities: $capacities,
                unitValue: intdiv($line->lineTotal, max(1, $line->quantity)),
                shipSeparately: (bool) ($sku['ship_separately'] ?? false),
                liquidOrFragile: (bool) ($sku['is_liquid'] ?? false) || (bool) ($sku['is_fragile'] ?? false),
            );
        }

        return $items;
    }

    /**
     * The coupon's rules as an order keeps them (D3.4 coupon_snapshot).
     *
     * @return array<string, mixed>|null
     */
    public function couponSnapshot(int $couponId): ?array
    {
        return $this->carts->couponSnapshot($couponId);
    }
}
