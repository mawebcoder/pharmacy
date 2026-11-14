<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\CouponUsage;

/**
 * The payment failed, was cancelled or the order expired (roadmap D13.5): the reservation is reverted, so its slot
 * counts no more. Only reserved uses are released; a used one stays used.
 */
final class ReleaseCoupon
{
    public function __invoke(CouponUsage $usage): void
    {
        if ($usage->getStatus() !== CouponUsageStatus::Reserved) {
            return;
        }

        $usage->setStatus(CouponUsageStatus::Released)
            ->setReleasedAt(now())
            ->save();
    }
}
