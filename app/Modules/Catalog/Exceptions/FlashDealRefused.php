<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use RuntimeException;

/**
 * A flash deal change or reservation that cannot be made (roadmap S33); the message is shown as is.
 */
final class FlashDealRefused extends RuntimeException
{
    public static function notCheaper(int $regularPrice): self
    {
        return new self('قیمت پیشنهاد باید کمتر از قیمت فعلی این تنوع ('.Toman::format($regularPrice).') باشد.');
    }

    public static function supplierRequired(): self
    {
        return new self('تأمین‌کننده این پیشنهاد را انتخاب کنید.');
    }

    public static function supplierLacksSku(): self
    {
        return new self('این تأمین‌کننده این تنوع را ندارد؛ از تأمین‌کنندگان همین تنوع انتخاب کنید.');
    }

    public static function overlaps(): self
    {
        return new self('این تنوع در همین بازه یک پیشنهاد لحظه‌ای فعال دیگر دارد.');
    }

    public static function belowSold(int $sold): self
    {
        return new self('سقف تعداد نمی‌تواند کمتر از تعداد فروخته‌شده ('.PersianDigits::toPersian($sold).') باشد.');
    }

    public static function alreadySold(): self
    {
        return new self('پیشنهادی که فروش داشته حذف نمی‌شود؛ آن را غیرفعال کنید.');
    }

    public static function soldOut(string $productName): self
    {
        return new self('پیشنهاد لحظه‌ای «'.$productName.'» تمام شد یا به پایان رسید؛ قیمت را دوباره ببینید.');
    }
}
