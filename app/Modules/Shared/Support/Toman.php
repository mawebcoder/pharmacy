<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Illuminate\Support\Number;

/**
 * Money is stored as integer rial everywhere; people see toman (rial ÷ 10), grouped in threes with Persian digits.
 * Example: Toman::format(12_500_000) → "۱٬۲۵۰٬۰۰۰ تومان".
 */
final class Toman
{
    public const string UNIT = 'تومان';

    /**
     * Rial → whole toman, rounded half up.
     */
    public static function fromRial(int|float|string|null $rial): int
    {
        return (int) round((float) $rial / 10);
    }

    /**
     * Toman → rial (for admin inputs that are typed in toman).
     */
    public static function toRial(int|float|string|null $toman): int
    {
        return (int) round((float) $toman * 10);
    }

    /**
     * A toman amount as typed by a person ("485,000", "۴۸۵٬۰۰۰") → rial (4_850_000). Blank input → null.
     */
    public static function rialFromInput(int|float|string|null $toman): ?int
    {
        if ($toman === null || $toman === '') {
            return null;
        }

        return self::toRial(str_replace([',', '٬', ' '], '', PersianDigits::toLatin((string) $toman)));
    }

    /**
     * "۱٬۲۵۰٬۰۰۰ تومان" (or "۱٬۲۵۰٬۰۰۰" without the unit).
     */
    public static function format(int|float|string|null $rial, bool $withUnit = true): string
    {
        $digits = (string) Number::format(self::fromRial($rial), locale: 'fa');

        return $withUnit ? $digits.' '.self::UNIT : $digits;
    }

    /**
     * Discount percent between an old and a new rial price, e.g. 15_000_000 → 12_500_000 gives 17.
     */
    public static function discountPercent(int $oldRial, int $newRial): int
    {
        if ($oldRial <= 0 || $newRial >= $oldRial) {
            return 0;
        }

        return (int) round(($oldRial - $newRial) / $oldRial * 100);
    }
}
