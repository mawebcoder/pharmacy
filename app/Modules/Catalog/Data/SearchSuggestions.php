<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the instant search box shows (roadmap D21.2 item 6): up to 6 products, 8 categories and 8 brands (matching the
 * text or behind the matched products; owner, 2026-11-15) and 3 tags. A category or brand row that matched nothing
 * holds the admin's pinned ones instead ($pinnedCategories, $pinnedBrands). Unavailable when product search could
 * not be reached (the other rows still work).
 */
final readonly class SearchSuggestions
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Brand>  $brands
     * @param  Collection<int, Category|Subcategory>  $categories  categories, then subcategories
     * @param  Collection<int, Tag>  $tags
     */
    public function __construct(
        public Collection $products,
        public Collection $brands,
        public Collection $categories,
        public Collection $tags,
        public bool $productsAvailable = true,
        public bool $pinnedCategories = false,
        public bool $pinnedBrands = false,
    ) {}

    /**
     * Whether nothing matched the text (pinned categories and brands are not matches).
     */
    public function isEmpty(): bool
    {
        return $this->products->isEmpty()
            && $this->tags->isEmpty()
            && ($this->pinnedCategories || $this->categories->isEmpty())
            && ($this->pinnedBrands || $this->brands->isEmpty());
    }
}
