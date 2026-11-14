<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\CouponUsage;

/**
 * The payment was verified (roadmap D13.5, called by S26): the reserved use becomes used. A verified payment is
 * honoured even when the reservation had just expired.
 */
final class MarkCouponUsed
{
    public function __invoke(CouponUsage $usage): void
    {
        if ($usage->getStatus() === CouponUsageStatus::Used) {
            return;
        }

        $usage->setStatus(CouponUsageStatus::Used)
            ->setUsedAt(now())
            ->setReservedUntil(null)
            ->save();
    }
}
