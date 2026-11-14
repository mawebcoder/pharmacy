<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Iranian mobile numbers (roadmap D13.1): any common way of typing one becomes «09xxxxxxxxx».
 * «۰۹۱۲ ۳۴۵ ۶۷۸۹», «+989123456789», «00989123456789», «989123456789» and «9123456789» all give «09123456789».
 * Only operator prefixes in use pass: 0901–0905, 0910–0919, 0920–0922, 0930–0939, 0941 and 0990–0999.
 */
final class IranMobile
{
    public const string PATTERN = '/^09(?:0[1-5]|1\d|2[0-2]|3\d|41|9\d)\d{7}$/';

    /**
     * The number as «09xxxxxxxxx», or null when it is not an Iranian mobile number.
     */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/u', '', PersianNormalizer::digits(trim($input))) ?? '';
        $digits = match (true) {
            str_starts_with($digits, '+98') => '0'.substr($digits, 3),
            str_starts_with($digits, '0098') => '0'.substr($digits, 4),
            str_starts_with($digits, '98') && strlen($digits) === 12 => '0'.substr($digits, 2),
            str_starts_with($digits, '9') && strlen($digits) === 10 => '0'.$digits,
            default => $digits,
        };

        return preg_match(self::PATTERN, $digits) === 1 ? $digits : null;
    }
}
