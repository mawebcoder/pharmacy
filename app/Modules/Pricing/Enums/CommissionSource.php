<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a SKU's commission came from (roadmap D3.2, S15b), most specific first: the SKU's supplier row, the product
 * for that supplier, the product, the brand for that supplier, the brand.
 */
enum CommissionSource: string implements HasLabel
{
    use LabelledEnum;

    case SkuProvider = 'sku_provider';
    case ProductProvider = 'product_provider';
    case Product = 'product';
    case BrandProvider = 'brand_provider';
    case Brand = 'brand';

    public function label(): string
    {
        return match ($this) {
            self::SkuProvider => 'SKU و تأمین‌کننده',
            self::ProductProvider => 'محصول و تأمین‌کننده',
            self::Product => 'کمیسیون محصول',
            self::BrandProvider => 'برند و تأمین‌کننده',
            self::Brand => 'کمیسیون برند',
        };
    }
}
