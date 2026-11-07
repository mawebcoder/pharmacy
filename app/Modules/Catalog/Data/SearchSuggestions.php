<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the instant search box shows (roadmap D21.2 item 6): up to 6 products, 3 brands, 3 categories, 3 tags.
 * Unavailable when product search could not be reached (brands, categories and tags still work).
 */
final readonly class SearchSuggestions
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Brand>  $brands
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Tag>  $tags
     */
    public function __construct(
        public Collection $products,
        public Collection $brands,
        public Collection $categories,
        public Collection $tags,
        public bool $productsAvailable = true,
    ) {}

    public function isEmpty(): bool
    {
        return $this->products->isEmpty() && $this->brands->isEmpty() && $this->categories->isEmpty() && $this->tags->isEmpty();
    }
}
