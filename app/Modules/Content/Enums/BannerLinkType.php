<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a banner leads (roadmap D3.6, S13b). The admin picks the item; the URL is worked out when the banner is
 * shown, so it follows slug changes. The catalog types are resolved through Catalog's CatalogLinks, static pages
 * (S34) through PageLinks.
 */
enum BannerLinkType: string implements HasLabel
{
    use LabelledEnum;

    case Category = 'category';
    case Subcategory = 'subcategory';
    case Collection = 'collection';
    case Brand = 'brand';
    case Tag = 'tag';
    case Product = 'product';
    case Page = 'page';
    case CategoriesList = 'categories_list';
    case CollectionsList = 'collections_list';
    case BrandsList = 'brands_list';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'یک دسته‌بندی',
            self::Subcategory => 'یک زیردسته',
            self::Collection => 'یک کالکشن',
            self::Brand => 'یک برند',
            self::Tag => 'یک برچسب',
            self::Product => 'یک محصول',
            self::Page => 'یک صفحه ثابت',
            self::CategoriesList => 'فهرست دسته‌بندی‌ها',
            self::CollectionsList => 'فهرست کالکشن‌ها',
            self::BrandsList => 'فهرست برندها',
            self::External => 'آدرس بیرونی',
        };
    }

    /**
     * Whether the admin picks an item (a category, brand, …, or a static page) for this target.
     */
    public function needsItem(): bool
    {
        return $this === self::Page || $this->isCatalogItem();
    }

    /**
     * Whether the target is a catalog item, resolved through Catalog's CatalogLinks.
     */
    public function isCatalogItem(): bool
    {
        return in_array($this, [self::Category, self::Subcategory, self::Collection, self::Brand, self::Tag, self::Product], true);
    }
}
