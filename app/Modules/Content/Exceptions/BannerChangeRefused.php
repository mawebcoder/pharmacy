<?php

declare(strict_types=1);

namespace App\Modules\Content\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use RuntimeException;

/**
 * A banner change that would break a rule; the message is shown to the admin.
 */
final class BannerChangeRefused extends RuntimeException
{
    public static function linkItemMissing(): self
    {
        return new self('مقصد بنر را انتخاب کنید.');
    }

    public static function externalUrlInvalid(): self
    {
        return new self('آدرس بیرونی باید با https:// شروع شود.');
    }

    public static function tooMany(int $max): self
    {
        return new self('در هر جایگاه حداکثر '.PersianDigits::toPersian($max).' بنر نمایش داده می‌شود.');
    }

    public static function placeItemMissing(): self
    {
        return new self('صفحه‌ای را که بنرها بالای آن نمایش داده می‌شوند انتخاب کنید.');
    }
}
