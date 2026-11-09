<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where banners are shown (roadmap D3.6, S13b): a home slider widget, the top of one category, collection, brand or
 * tag page, or the top of a list page. Each place shows at most 6 banners, as a slider when there are several.
 */
enum BannerPlace: string implements HasLabel
{
    use LabelledEnum;

    case HomeWidget = 'home_widget';
    case Category = 'category';
    case Collection = 'collection';
    case Brand = 'brand';
    case Tag = 'tag';
    case CategoriesList = 'categories_list';
    case CollectionsList = 'collections_list';
    case BrandsList = 'brands_list';

    public const int MAX_BANNERS = 6;

    public function label(): string
    {
        return match ($this) {
            self::HomeWidget => 'اسلایدر صفحه اصلی',
            self::Category => 'بالای صفحه یک دسته‌بندی',
            self::Collection => 'بالای صفحه یک کالکشن',
            self::Brand => 'بالای صفحه یک برند',
            self::Tag => 'بالای صفحه یک برچسب',
            self::CategoriesList => 'بالای فهرست دسته‌بندی‌ها',
            self::CollectionsList => 'بالای فهرست کالکشن‌ها',
            self::BrandsList => 'بالای فهرست برندها',
        };
    }

    /**
     * The catalog type of the page's item (CatalogLinks), or null for the list pages and home widgets.
     */
    public function catalogType(): ?string
    {
        return match ($this) {
            self::Category => 'category',
            self::Collection => 'collection',
            self::Brand => 'brand',
            self::Tag => 'tag',
            default => null,
        };
    }
}
