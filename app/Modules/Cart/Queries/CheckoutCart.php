<?php

declare(strict_types=1);

namespace App\Modules\Cart\Queries;

use App\Modules\Cart\Actions\ValidateCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Data\CartOwnerData;
use App\Modules\Cart\Data\CartSummaryData;
use App\Modules\Cart\Data\CheckoutCartData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;

/**
 * What the Checkout module reads from Cart (roadmap S23, D3.4): the customer's cart now, a coupon code checked against
 * its lines (nothing is written), and a coupon's rules as an order keeps them.
 */
final readonly class CheckoutCart
{
    public function __construct(
        private CurrentCart $carts,
        private ValidateCoupon $validateCoupon,
    ) {}

    public function forCustomer(int $customerId): ?CheckoutCartData
    {
        $cart = $this->carts->find(new CartOwnerData($customerId, null));

        return $cart instanceof Cart
            ? new CheckoutCartData($cart->getId(), $cart->getAddressId(), $this->carts->summaryOf($cart))
            : null;
    }

    /**
     * SKUs and quantities priced now, without a cart (owner, 2026-11-19: panel orders and unpaid orders being edited).
     *
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function summaryFor(array $quantities): CartSummaryData
    {
        return $this->carts->summaryForQuantities($quantities);
    }

    /**
     * @param  list<CartLineData>  $lines
     *
     * @throws CouponRefused when the code is unknown or does not apply
     */
    public function coupon(string $code, array $lines, int $customerId): AppliedCouponData
    {
        $coupon = Coupon::query()->where(Coupon::COLUMN_CODE, Coupon::normalizeCode($code))->first() ?? throw CouponRefused::notFound();

        return ($this->validateCoupon)($coupon, $lines, $customerId);
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
