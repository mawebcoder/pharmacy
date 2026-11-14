<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Shared\Support\JalaliDate;
use DateTimeInterface;

/**
 * Human order numbers (roadmap D3.4): «PH-» + Jalali year and month + the order id in 6 digits, e.g. PH-140508-000123
 * for order 123 placed in Aban 1405. Unique because the id is.
 */
final class OrderNumber
{
    public static function for(int $orderId, DateTimeInterface $placedAt): string
    {
        return 'PH-'.JalaliDate::format($placedAt, 'Ym').'-'.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
    }
}
