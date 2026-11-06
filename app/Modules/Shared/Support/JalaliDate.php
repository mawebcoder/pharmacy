<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Ariaieboy\Jalali\Jalali;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formats timestamps (stored in UTC) as Jalali dates in the display timezone (Asia/Tehran).
 */
final class JalaliDate
{
    /**
     * Latin digits, for data and comparisons: "1405/08/15".
     */
    public static function format(DateTimeInterface $date, string $format = 'Y/m/d'): string
    {
        $local = Carbon::instance($date)->setTimezone((string) config('app.display_timezone'));

        return Jalali::fromCarbon($local)->format($format);
    }

    /**
     * Persian digits, for the UI: "۱۵ آبان ۱۴۰۵".
     */
    public static function display(?DateTimeInterface $date, string $format = 'j F Y'): string
    {
        return $date instanceof DateTimeInterface ? PersianDigits::toPersian(self::format($date, $format)) : '';
    }
}
