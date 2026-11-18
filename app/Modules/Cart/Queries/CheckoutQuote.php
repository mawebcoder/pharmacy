<?php

declare(strict_types=1);

namespace App\Modules\Cart\Queries;

use App\Modules\Cart\Actions\ValidateCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CheckoutQuoteData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Catalog\Queries\OrderItemSnapshots;
use App\Modules\Identity\Queries\CustomerAddresses;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Queries\ShippingOptions;

/**
 * Works out checkout from the customer's cart as it is now (roadmap S23, S34): current prices and stock, the coupon
 * checked on the fly (nothing is written), the picked address or else the default, and the shipping options for it:
 * the cart packed into our boxes and priced by the shipping services (Tipax), the flat rate when none answers. Free
 * shipping (threshold or coupon) covers the cheapest option. PlaceOrder uses the same quote, so what is charged is
 * what was shown.
 */
final readonly class CheckoutQuote
{
    public function __construct(
        private CurrentCart $carts,
        private ValidateCoupon $validateCoupon,
        private StoreSettings $settings,
        private CustomerAddresses $addresses,
        private ShippingOptions $shippingOptions,
        private OrderItemSnapshots $itemSnapshots,
    ) {}

    /**
     * The customer's checkout now, or null when they have no cart. $shippingOptionKey is the option they picked; the
     * cheapest stands in when it is missing or no longer offered.
     */
    public function forCustomer(int $customerId, ?string $couponCode, ?string $shippingOptionKey = null): ?CheckoutQuoteData
    {
        $cart = $this->carts->find(new CartOwnerData($customerId, null));

        return $cart instanceof Cart ? $this->for($cart, $customerId, $couponCode, $shippingOptionKey) : null;
    }

    public function for(Cart $cart, int $customerId, ?string $couponCode, ?string $shippingOptionKey = null): CheckoutQuoteData
    {
        $summary = $this->carts->summaryOf($cart);
        $coupon = null;
        $problem = null;

        if ($couponCode !== null && $couponCode !== '') {
            try {
                $model = Coupon::query()->where(Coupon::COLUMN_CODE, Coupon::normalizeCode($couponCode))->first() ?? throw CouponRefused::notFound();
                $coupon = ($this->validateCoupon)($model, $summary->lines, $customerId);
            } catch (CouponRefused $refused) {
                $problem = $refused->getMessage();
            }
        }

        $threshold = $this->settings->freeShippingThreshold();
        $freeByThreshold = $threshold > 0 && $summary->subtotal >= $threshold;
        $freeByCoupon = $coupon instanceof AppliedCouponData && $coupon->freeShipping;
        $free = $freeByThreshold || $freeByCoupon;
        $addressId = $this->addresses->chosenId($customerId, $cart->getAddressId());
        $address = $addressId === null ? null : $this->addresses->snapshot($customerId, $addressId);
        $options = $this->shippingOptions->for($address?->cityId, $this->packItems($summary->lines));
        $chosen = $options[0];

        foreach ($options as $option) {
            if ($option->key === $shippingOptionKey) {
                $chosen = $option;
            }
        }

        $discount = $free ? min($chosen->price, $options[0]->price) : 0;

        return new CheckoutQuoteData(
            cartId: $cart->getId(),
            cart: $summary->withCoupon($coupon),
            coupon: $coupon,
            couponProblem: $problem,
            shippingOptions: $options,
            shippingOption: $chosen,
            shippingDiscount: $discount,
            shippingPrice: $chosen->price - $discount,
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
     * The coupon's rules as an order keeps them (D3.4 coupon_snapshot), so later edits never change the order.
     *
     * @return array<string, mixed>|null
     */
    public function couponSnapshot(int $couponId): ?array
    {
        $coupon = Coupon::query()->withTrashed()->find($couponId);

        if (! $coupon instanceof Coupon) {
            return null;
        }

        return [
            'code' => $coupon->getCode(),
            'type' => $coupon->getType()->value,
            'value' => $coupon->getValue(),
            'max_discount' => $coupon->getMaxDiscount(),
            'min_order_amount' => $coupon->getMinOrderAmount(),
            'applies_to_sale_items' => $coupon->appliesToSaleItems(),
            'first_order_only' => $coupon->isFirstOrderOnly(),
            'category_ids' => $coupon->includedIds('coupon_categories'),
            'brand_ids' => $coupon->includedIds('coupon_brands'),
            'product_ids' => $coupon->includedIds('coupon_products'),
        ];
    }
}
