<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
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
     * Products of a category or any category below it (primary or extra category).
     */
    public static function refreshCategory(Category $category): void
    {
        $ids = $category->subtreeIds();

        self::refreshWhere(Product::query()->where(fn (Builder $query) => $query
            ->whereIn(Product::COLUMN_PRIMARY_CATEGORY_ID, $ids)
            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $ids))));
    }
}
