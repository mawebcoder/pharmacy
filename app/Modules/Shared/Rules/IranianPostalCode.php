<?php

declare(strict_types=1);

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\PersianNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Iranian postal code: 10 digits, not starting with 0 (Persian or Latin digits, spaces and dashes ignored).
 */
final class IranianPostalCode implements ValidationRule
{
    public static function normalize(string $input): string
    {
        return preg_replace('/[\s\-]/u', '', PersianNormalizer::digits(trim($input))) ?? '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[1-9]\d{9}$/', self::normalize($value)) !== 1) {
            $fail('کد پستی باید ۱۰ رقم باشد.');
        }
    }
}
