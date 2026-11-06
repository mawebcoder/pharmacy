<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Converts digits between Latin (0-9), Persian (۰-۹) and Arabic-Indic (٠-٩).
 * The UI shows Persian digits; input is normalised to Latin before validation and storage.
 */
final class PersianDigits
{
    private const array LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const array PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const array ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * "1405/08/15" → "۱۴۰۵/۰۸/۱۵". Arabic-Indic digits are converted too.
     */
    public static function toPersian(string|int|float $value): string
    {
        return str_replace([...self::LATIN, ...self::ARABIC], [...self::PERSIAN, ...self::PERSIAN], (string) $value);
    }

    /**
     * "۰۹۱۲۱۲۳۴۵۶۷" or "٠٩١٢١٢٣٤٥٦٧" → "09121234567".
     */
    public static function toLatin(string $value): string
    {
        return str_replace([...self::PERSIAN, ...self::ARABIC], [...self::LATIN, ...self::LATIN], $value);
    }
}
