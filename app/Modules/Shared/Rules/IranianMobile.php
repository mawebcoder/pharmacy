<?php

declare(strict_types=1);

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\IranMobile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Iranian mobile number on a real operator prefix (IranMobile::PATTERN), typed in any common way
 * («۰۹۱۲ ۳۴۵ ۶۷۸۹», «+98912…»). Normalise the value with IranMobile::normalize() before storing it.
 */
final class IranianMobile implements ValidationRule
{
    public const string MESSAGE = 'شماره موبایل معتبر نیست؛ مثلاً ۰۹۱۲۳۴۵۶۷۸۹';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || IranMobile::normalize($value) === null) {
            $fail(self::MESSAGE);
        }
    }
}
