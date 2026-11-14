<?php

declare(strict_types=1);

namespace App\Modules\Cart\Jobs;

use App\Modules\Cart\Actions\ReleaseCoupon;
use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\CouponUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reverts coupon reservations whose payment never completed in time (roadmap S20). They already stop counting toward
 * limits once expired; this records the release. Runs every five minutes.
 */
final class ReleaseExpiredCouponReservations implements ShouldQueue
{
    use Queueable;

    public function handle(ReleaseCoupon $releaseCoupon): void
    {
        CouponUsage::query()
            ->where(CouponUsage::COLUMN_STATUS, CouponUsageStatus::Reserved)
            ->where(CouponUsage::COLUMN_RESERVED_UNTIL, '<=', now())
            ->each(fn (CouponUsage $usage) => $releaseCoupon($usage));
    }
}
