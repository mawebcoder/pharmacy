<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use RuntimeException;

/**
 * A product or variant change that would break a sales rule (roadmap D0 rule 9, D3.1).
 */
final class ProductChangeRefused extends RuntimeException
{
    /**
     * @param  list<string>  $missing  what the product still needs, in Persian
     */
    public static function cannotActivate(array $missing): self
    {
        return new self('محصول فعال نشد: '.implode('؛ ', $missing).'.');
    }

    public static function salePriceNotBelowPrice(): self
    {
        return new self('قیمت حراج باید کمتر از قیمت فروش باشد.');
    }

    public static function aboveOfficialPrice(int $officialPriceToman): self
    {
        return new self('قیمت فروش نمی‌تواند از قیمت مصرف‌کننده روی جعبه ('.PersianDigits::toPersian(number_format($officialPriceToman)).' تومان) بیشتر باشد.');
    }

    public static function officialPriceRequired(): self
    {
        return new self('برای دارو، قیمت مصرف‌کننده (روی جعبه) لازم است.');
    }

    public static function lastActiveVariant(): self
    {
        return new self('این تنها تنوع فعال یک محصول فعال است. ابتدا محصول را غیرفعال کنید یا تنوع فعال دیگری اضافه کنید.');
    }

    public static function lastVariant(): self
    {
        return new self('هر محصول دست‌کم یک تنوع دارد؛ آخرین تنوع را نمی‌توان حذف کرد. به‌جای آن، تنوع را ویرایش یا غیرفعال کنید.');
    }

    public static function defaultVariantNotUsable(): self
    {
        return new self('فقط یک تنوع فعال از همین محصول می‌تواند SKU پیش‌فرض باشد.');
    }

    public static function relatedToItself(): self
    {
        return new self('محصول نمی‌تواند به خودش مرتبط شود.');
    }
}
