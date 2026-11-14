<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\DB;

/**
 * Fills the system collection «محصولات محبوب» with the given products in rank order (owner, 2026-11-14: the list is
 * worked out every night from search clicks, Search\Jobs\RecalculatePopularProducts). Only active products are kept;
 * with none left the current list stays, so a quiet store never ends up with an empty list. Products that join or
 * leave are refreshed in the search index (collections are a filter there).
 */
final class ReplacePopularProducts
{
    /**
     * @param  list<int>  $productIds  best first
     */
    public function __invoke(array $productIds): void
    {
        $collection = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->first();
        $active = Product::query()->active()->whereKey($productIds)->pluck(Product::COLUMN_ID)->map(intval(...))->all();
        $ranked = array_values(array_filter(array_unique($productIds), fn (int $id): bool => in_array($id, $active, true)));

        if (! $collection instanceof ProductCollection || $ranked === []) {
            return;
        }

        $before = $collection->products()->pluck('products.id')->map(intval(...))->all();

        DB::transaction(function () use ($collection, $ranked): void {
            $sync = [];

            foreach ($ranked as $position => $productId) {
                $sync[$productId] = [ProductCollection::PIVOT_SORT => $position + 1];
            }

            $collection->products()->sync($sync);
        });

        $changed = array_values(array_merge(array_diff($ranked, $before), array_diff($before, $ranked)));

        if ($changed !== []) {
            ProductSearchIndex::refreshWhere(Product::query()->whereKey($changed));
        }
    }
}
