<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CouponCodeData;
use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\CouponUsage;

/**
 * The customer applies a code at checkout (roadmap S20, called by S25): the discount is worked out on the fly and the
 * use is logged as «checkout», which does not count toward the coupon's limits. Applying the same code again
 * refreshes that log instead of adding rows.
 */
final readonly class ApplyCouponAtCheckout
{
    public function __construct(private QuoteCoupon $quoteCoupon) {}

    /**
     * @throws CouponRefused
     */
    #[\NoDiscard]
    public function __invoke(CouponCodeData $data): AppliedCouponData
    {
        $applied = ($this->quoteCoupon)($data);

        $usage = CouponUsage::query()
            ->where(CouponUsage::COLUMN_COUPON_ID, $applied->couponId)
            ->where(CouponUsage::COLUMN_CUSTOMER_ID, $data->customerId)
            ->where(CouponUsage::COLUMN_STATUS, CouponUsageStatus::Checkout)
            ->latest(CouponUsage::COLUMN_ID)
            ->first() ?? (new CouponUsage)->setCouponId($applied->couponId)->setCustomerId($data->customerId)->setStatus(CouponUsageStatus::Checkout);

        $usage->setDiscountAmount($applied->discount)->touch();

        return $applied;
    }
}
