<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Database\Eloquent\Collection;

/**
 * The first products of a collection a shopper can buy now, in the admin's order: for the empty search box
 * («محصولات محبوب», roadmap D21.2) and the home page's collection widgets (at most 6 each, S13b). Products that are not
 * for sale or whose default SKU is out of stock are skipped. Loaded with what product cards need.
 */
final readonly class CollectionProducts
{
    public const int WIDGET_LIMIT = 6;

    /**
     * Products looked at before giving up on filling the list with in-stock ones.
     */
    private const int SCAN_LIMIT = 60;

    /**
     * @return Collection<int, Product>
     */
    public function popular(int $limit = self::WIDGET_LIMIT): Collection
    {
        $collection = ProductCollection::query()->visible()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->first();

        return $collection instanceof ProductCollection ? $this->of($collection, $limit) : new Collection;
    }

    /**
     * @return Collection<int, Product>
     */
    public function forCollection(int $collectionId, int $limit = self::WIDGET_LIMIT): Collection
    {
        $collection = ProductCollection::query()->visible()->find($collectionId);

        return $collection instanceof ProductCollection ? $this->of($collection, $limit) : new Collection;
    }

    /**
     * @return Collection<int, Product>
     */
    private function of(ProductCollection $collection, int $limit): Collection
    {
        return $collection->products()
            ->active()
            ->with(ProductListing::CARD_RELATIONS)
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->filter(fn (Product $product): bool => $product->isSellableType() && ($product->getDefaultVariant()?->isInStock() ?? false))
            ->take(min($limit, self::WIDGET_LIMIT))
            ->values();
    }
}
