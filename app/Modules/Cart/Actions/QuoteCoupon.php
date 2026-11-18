<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CouponCodeData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Queries\CurrentCart;

/**
 * Works out a coupon's discount for the customer's cart as it is now (roadmap S22), for checkout to show on the fly.
 * It writes nothing; ApplyCouponAtCheckout logs the code once the customer applies it.
 */
final readonly class QuoteCoupon
{
    public function __construct(
        private ValidateCoupon $validateCoupon,
        private CurrentCart $carts,
    ) {}

    /**
     * @throws CouponRefused
     */
    #[\NoDiscard]
    public function __invoke(CouponCodeData $data): AppliedCouponData
    {
        $cart = Cart::query()->findOrFail($data->cartId);
        $coupon = Coupon::query()->where(Coupon::COLUMN_CODE, $data->code)->first() ?? throw CouponRefused::notFound();

        return ($this->validateCoupon)($coupon, $this->carts->summaryOf($cart)->lines, $data->customerId);
    }
}
