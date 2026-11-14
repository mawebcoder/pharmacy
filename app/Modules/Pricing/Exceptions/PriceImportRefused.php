<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Exceptions;

use RuntimeException;

/**
 * A price import step that cannot run now; the message is shown to the admin.
 */
final class PriceImportRefused extends RuntimeException
{
    public static function notPreviewed(): self
    {
        return new self('فقط ورودی که بررسی و پیش‌نمایش شده است قابل اعمال است.');
    }

    public static function nothingToApply(): self
    {
        return new self('این فایل ردیفی برای تغییر ندارد.');
    }
}
