<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\CouponUsage;

/**
 * Settles the coupon reserved for an order (roadmap D13.5, S25): used once the order is paid, released when it
 * expires or fails (a payment verified after the order expired still marks it used). Nothing happens for an order
 * without a coupon.
 */
final readonly class SettleOrderCoupon
{
    public function __construct(
        private MarkCouponUsed $markUsed,
        private ReleaseCoupon $release,
    ) {}

    public function __invoke(int $orderId, bool $paid): void
    {
        $usage = CouponUsage::query()
            ->where(CouponUsage::COLUMN_ORDER_ID, $orderId)
            ->whereIn(CouponUsage::COLUMN_STATUS, $paid
                ? [CouponUsageStatus::Reserved, CouponUsageStatus::Used, CouponUsageStatus::Released]
                : [CouponUsageStatus::Reserved])
            ->latest(CouponUsage::COLUMN_ID)
            ->first();

        if ($usage instanceof CouponUsage) {
            $paid ? ($this->markUsed)($usage) : ($this->release)($usage);
        }
    }
}
