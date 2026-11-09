<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a banner leads (roadmap D3.6, S13b). The admin picks the item; the URL is worked out when the banner is
 * shown, so it follows slug changes. The catalog types are resolved through Catalog's CatalogLinks.
 */
enum BannerLinkType: string implements HasLabel
{
    use LabelledEnum;

    case Category = 'category';
    case Collection = 'collection';
    case Brand = 'brand';
    case Tag = 'tag';
    case Product = 'product';
    case CategoriesList = 'categories_list';
    case CollectionsList = 'collections_list';
    case BrandsList = 'brands_list';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'یک دسته‌بندی',
            self::Collection => 'یک کالکشن',
            self::Brand => 'یک برند',
            self::Tag => 'یک برچسب',
            self::Product => 'یک محصول',
            self::CategoriesList => 'فهرست دسته‌بندی‌ها',
            self::CollectionsList => 'فهرست کالکشن‌ها',
            self::BrandsList => 'فهرست برندها',
            self::External => 'آدرس بیرونی',
        };
    }

    /**
     * Whether the admin picks a catalog item (a category, brand, …) for this target.
     */
    public function needsItem(): bool
    {
        return in_array($this, [self::Category, self::Collection, self::Brand, self::Tag, self::Product], true);
    }
}
