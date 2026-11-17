<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a banner or a menu item leads (roadmap D3.6, S13b, S34). The admin picks the item; the URL is worked out when
 * it is shown (LinkUrls), so it follows slug changes. The catalog types are resolved through Catalog's CatalogLinks,
 * static pages through PageLinks, the site pages by route name.
 */
enum LinkType: string implements HasLabel
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
    case AllProducts = 'products';
    case Deals = 'deals';
    case Faq = 'faq';
    case Contact = 'contact';
    case TrackOrder = 'track';
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
            self::AllProducts => 'همه کالاها',
            self::Deals => 'پیشنهادهای لحظه‌ای',
            self::Faq => 'پرسش‌های متداول',
            self::Contact => 'تماس با ما',
            self::TrackOrder => 'پیگیری سفارش',
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

    /**
     * The route of a site page target (a list page or a fixed page such as «تماس با ما»), else null.
     */
    public function routeName(): ?string
    {
        return match ($this) {
            self::CategoriesList => 'categories.index',
            self::CollectionsList => 'collections.index',
            self::BrandsList => 'brands.index',
            self::AllProducts => 'products.index',
            self::Deals => 'deals',
            self::Faq => 'faq',
            self::Contact => 'contact',
            self::TrackOrder => 'orders.track',
            default => null,
        };
    }
}
