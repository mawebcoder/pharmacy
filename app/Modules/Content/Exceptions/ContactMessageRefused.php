<?php

declare(strict_types=1);

namespace App\Modules\Content\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use RuntimeException;

/**
 * A contact message that was not sent; the message is shown on the form.
 */
final class ContactMessageRefused extends RuntimeException
{
    public static function tooMany(int $secondsLeft): self
    {
        return new self('پیام‌های شما زیاد بود؛ '.PersianDigits::toPersian(max(1, (int) ceil($secondsLeft / 60))).' دقیقه دیگر دوباره تلاش کنید.');
    }
}
