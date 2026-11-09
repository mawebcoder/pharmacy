<?php

declare(strict_types=1);

namespace App\Modules\Content\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use RuntimeException;

/**
 * A home widget change that would break a rule; the message is shown to the admin.
 */
final class HomeWidgetChangeRefused extends RuntimeException
{
    public static function collectionMissing(): self
    {
        return new self('کالکشن این ویجت را انتخاب کنید.');
    }

    public static function itemsCount(int $max): self
    {
        return new self('بین ۱ تا '.PersianDigits::toPersian($max).' مورد انتخاب کنید.');
    }
}
