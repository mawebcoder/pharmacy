<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use Carbon\CarbonImmutable;

/**
 * "زودمصرف": expiring within config('catalog.near_expiry_days') days (roadmap D9 MarkNearExpiry), counted in
 * Tehran days.
 */
final class NearExpiry
{
    public static function isNear(?CarbonImmutable $expiryDate): bool
    {
        return $expiryDate instanceof CarbonImmutable && $expiryDate->lessThanOrEqualTo(self::cutoff());
    }

    /**
     * The last expiry date that counts as near expiry, as of today.
     */
    public static function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('app.display_timezone'))->startOfDay()->addDays((int) config('catalog.near_expiry_days', 90));
    }
}
