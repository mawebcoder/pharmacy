<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Jobs\ReindexProducts;
use App\Modules\Catalog\Models\Product;

/**
 * The only way product documents reach Meilisearch (owner, 2026-11-27: event-driven, bulk). Scout does not index
 * products on save (CatalogServiceProvider turns that off), because a product's document also depends on its SKUs,
 * links, brand and categories, which change elsewhere. Search events (Catalog\Events\*SearchFieldsChanged) name the
 * affected products; QueueProductReindex queues them here in chunks; `search:reconcile` re-pushes everything hourly.
 *
 * A chunk is loaded with its relations in one query set, then sent with one call to add every product that is not
 * deleted (hidden ones too, flagged is_visible = false; owner, 2026-11-27) and one to remove the deleted or gone.
 */
final class ProductSearchIndex
{
    public const int CHUNK = 500;

    /**
     * Rebuilds these products' documents now.
     *
     * @param  array<int|string>  $productIds
     */
    public static function reindex(array $productIds): void
    {
        foreach (array_chunk(self::ids($productIds), self::CHUNK) as $chunk) {
            $products = Product::withTrashed()->with(ProductDocument::RELATIONS)->whereKey($chunk)->get();
            $indexed = $products->filter(fn (Product $product): bool => $product->shouldBeSearchable())->values();
            $removed = $products->reject(fn (Product $product): bool => $product->shouldBeSearchable())->values();

            // Products deleted for good are removed by id.
            foreach (array_diff($chunk, $products->modelKeys()) as $missingId) {
                $removed->push((new Product)->forceFill([Product::COLUMN_ID => $missingId]));
            }

            (new Product)->syncMakeSearchable($indexed);
            (new Product)->syncRemoveFromSearch($removed);
        }
    }

    /**
     * Rebuilds these products' documents in queued chunks (on the "search" queue when Scout queues), or at once.
     *
     * @param  array<int|string>  $productIds
     */
    public static function queue(array $productIds): void
    {
        foreach (array_chunk(self::ids($productIds), self::CHUNK) as $chunk) {
            config('scout.queue') ? ReindexProducts::dispatch($chunk) : ReindexProducts::dispatchSync($chunk);
        }
    }

    public static function refresh(Product $product): void
    {
        self::reindex([$product->getId()]);
    }

    /**
     * @param  array<int|string>  $productIds
     * @return list<int>
     */
    private static function ids(array $productIds): array
    {
        return array_values(array_unique(array_filter(array_map(intval(...), $productIds), fn (int $id): bool => $id > 0)));
    }
}
