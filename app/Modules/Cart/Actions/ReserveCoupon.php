<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use Illuminate\Support\Facades\DB;

/**
 * Reserves one use of a coupon when the payment link is created and the customer goes to the gateway (roadmap
 * D13.4; called by S23/S36 after validating the coupon against the order). The coupon row is locked and its rules
 * re-checked, so two customers paying at the same moment can never pass the total or per-customer limit. The
 * reservation lasts as long as a pending payment (config payments.pending_expiry_minutes); the customer's checkout
 * log row becomes the reservation.
 */
final readonly class ReserveCoupon
{
    public function __construct(private ValidateCoupon $validateCoupon) {}

    /**
     * @throws CouponRefused
     */
    #[\NoDiscard]
    public function __invoke(int $couponId, int $customerId, int $discountAmount, ?int $orderId = null): CouponUsage
    {
        return DB::transaction(function () use ($couponId, $customerId, $discountAmount, $orderId): CouponUsage {
            $coupon = Coupon::query()->withTrashed()->lockForUpdate()->find($couponId) ?? throw CouponRefused::notFound();

            $this->validateCoupon->checkRules($coupon, $customerId);

            $usage = CouponUsage::query()
                ->where(CouponUsage::COLUMN_COUPON_ID, $coupon->getId())
                ->where(CouponUsage::COLUMN_CUSTOMER_ID, $customerId)
                ->where(CouponUsage::COLUMN_STATUS, CouponUsageStatus::Checkout)
                ->latest(CouponUsage::COLUMN_ID)
                ->first() ?? (new CouponUsage)->setCouponId($coupon->getId())->setCustomerId($customerId);

            $usage->setStatus(CouponUsageStatus::Reserved)
                ->setDiscountAmount($discountAmount)
                ->setOrderId($orderId)
                ->setReservedUntil(now()->addMinutes((int) config('payments.pending_expiry_minutes')))
                ->save();

            return $usage;
        });
    }
}
