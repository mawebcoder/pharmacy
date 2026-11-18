<?php

declare(strict_types=1);

namespace App\Modules\Cart\Queries;

use App\Modules\Cart\Actions\ValidateCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CheckoutQuoteData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Identity\Queries\CustomerAddresses;
use App\Modules\Settings\Queries\StoreSettings;

/**
 * Works out checkout from the customer's cart as it is now (roadmap S23): current prices and stock, the coupon checked
 * on the fly (nothing is written), shipping at the flat rate («هزینه ارسال») unless the items reach the free-shipping
 * threshold or the coupon makes it free, and the picked address or else the default. PlaceOrder uses the same
 * quote, so what is charged is what was shown.
 */
final readonly class CheckoutQuote
{
    public function __construct(
        private CurrentCart $carts,
        private ValidateCoupon $validateCoupon,
        private StoreSettings $settings,
        private CustomerAddresses $addresses,
    ) {}

    /**
     * The customer's checkout now, or null when they have no cart.
     */
    public function forCustomer(int $customerId, ?string $couponCode): ?CheckoutQuoteData
    {
        $cart = $this->carts->find(new CartOwnerData($customerId, null));

        return $cart instanceof Cart ? $this->for($cart, $customerId, $couponCode) : null;
    }

    public function for(Cart $cart, int $customerId, ?string $couponCode): CheckoutQuoteData
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

        return new CheckoutQuoteData(
            cartId: $cart->getId(),
            cart: $summary->withCoupon($coupon),
            coupon: $coupon,
            couponProblem: $problem,
            shippingPrice: $free ? 0 : $this->settings->shippingFlatRate(),
            freeShipping: $free,
            flatRate: $this->settings->shippingFlatRate(),
            freeShippingThreshold: $threshold,
            freeShippingReason: $freeByCoupon ? 'coupon' : ($freeByThreshold ? 'threshold' : null),
            address: $addressId === null ? null : $this->addresses->snapshot($customerId, $addressId),
        );
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
