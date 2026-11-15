<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Keeps the Meilisearch product index in step with changes Scout does not see on its own: a variant, brand, tag,
 * category or attribute value changed, so the documents of the products using it are rebuilt (or removed when the
 * product is no longer for sale). Product saves themselves are handled by Scout.
 */
final class ProductSearchIndex
{
    public static function refresh(Product $product): void
    {
        $product->shouldBeSearchable() ? $product->searchable() : $product->unsearchable();
    }

    /**
     * @param  Builder<Product>  $products
     */
    public static function refreshWhere(Builder $products): void
    {
        $products->chunkById(100, function (Collection $chunk): void {
            $chunk->each(fn (Product $product) => self::refresh($product));
        });
    }

    /**
     * Products of a category: main or extra category, or placed in one of its subcategories.
     */
    public static function refreshCategory(Category $category): void
    {
        self::refreshWhere(Product::query()->where(fn (Builder $query) => $query
            ->where(Product::COLUMN_PRIMARY_CATEGORY_ID, $category->getId())
            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereKey($category->getId()))
            ->orWhereHas('subcategories', fn (Builder $subcategories) => $subcategories->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId()))));
    }

    /**
     * Products of a subcategory: main or extra subcategory.
     */
    public static function refreshSubcategory(Subcategory $subcategory): void
    {
        self::refreshWhere(Product::query()->where(fn (Builder $query) => $query
            ->where(Product::COLUMN_PRIMARY_SUBCATEGORY_ID, $subcategory->getId())
            ->orWhereHas('subcategories', fn (Builder $subcategories) => $subcategories->whereKey($subcategory->getId()))));
    }
}
