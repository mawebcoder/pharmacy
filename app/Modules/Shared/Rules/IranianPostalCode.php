<?php

declare(strict_types=1);

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\PersianNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Iranian postal code (Persian or Latin digits, spaces and dashes ignored): 10 digits, the first five never 0 or 2
 * (the national post's format), and not one digit repeated.
 */
final class IranianPostalCode implements ValidationRule
{
    public static function normalize(string $input): string
    {
        return preg_replace('/[\s\-]/u', '', PersianNormalizer::digits(trim($input))) ?? '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $postalCode = is_string($value) ? self::normalize($value) : '';

        if (preg_match('/^\d{10}$/', $postalCode) !== 1) {
            $fail('کد پستی باید ۱۰ رقم باشد.');

            return;
        }

        if (preg_match('/^[13-9]{5}\d{5}$/', $postalCode) !== 1 || preg_match('/^(\d)\1{9}$/', $postalCode) === 1) {
            $fail('کد پستی معتبر نیست.');
        }
    }
}
