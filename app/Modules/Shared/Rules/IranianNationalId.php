<?php

declare(strict_types=1);

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\IranNationalId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A valid Iranian national id (کد ملی), Persian or Latin digits.
 */
final class IranianNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || IranNationalId::normalize($value) === null) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
