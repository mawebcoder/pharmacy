<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Iranian national ids (کد ملی): 10 digits whose last digit is the checksum of the first nine.
 */
final class IranNationalId
{
    /**
     * The id as 10 Latin digits, or null when it is not a valid national id.
     */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s\-]/u', '', PersianNormalizer::digits(trim($input))) ?? '';

        if (preg_match('/^\d{10}$/', $digits) !== 1 || count(array_unique(str_split($digits))) === 1) {
            return null;
        }

        $sum = 0;

        foreach (range(0, 8) as $position) {
            $sum += (int) $digits[$position] * (10 - $position);
        }

        $remainder = $sum % 11;
        $check = $remainder < 2 ? $remainder : 11 - $remainder;

        return (int) $digits[9] === $check ? $digits : null;
    }
}
