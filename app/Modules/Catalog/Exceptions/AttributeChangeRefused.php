<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Shared\Support\PersianDigits;
use RuntimeException;

/**
 * An attribute change that would leave products with values that no longer exist or no longer fit.
 */
final class AttributeChangeRefused extends RuntimeException
{
    public static function attributeInUse(string $name): self
    {
        return new self("ویژگی «{$name}» روی محصولات استفاده شده و حذف نمی‌شود. ابتدا مقدارهایش را از محصولات بردارید.");
    }

    public static function valueInUse(string $value, int $productCount): self
    {
        return new self("مقدار «{$value}» روی ".PersianDigits::toPersian($productCount).' محصول استفاده شده و حذف نمی‌شود.');
    }

    public static function cannotBecomeSingle(string $name): self
    {
        return new self("برخی محصولات برای «{$name}» چند مقدار دارند؛ ابتدا آن‌ها را به یک مقدار کاهش دهید.");
    }

    public static function valueOfDeletedAttribute(): self
    {
        return new self('یکی از مقدارها متعلق به ویژگی حذف‌شده است.');
    }

    public static function tooManyValues(string $name): self
    {
        return new self("برای «{$name}» فقط یک مقدار می‌توان انتخاب کرد.");
    }

    public static function duplicateValue(string $value): self
    {
        return new self("مقدار «{$value}» در این ویژگی وجود دارد.");
    }
}
