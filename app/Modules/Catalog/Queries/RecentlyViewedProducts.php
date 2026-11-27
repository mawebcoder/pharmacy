<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * The shopper's recently viewed products (roadmap B9.5, S13) for the rail under the product page. The ids come from
 * the browser, so they are only trusted as a list of numbers: inactive or missing products are left out.
 */
final readonly class RecentlyViewedProducts
{
    public const int LIMIT = 12;

    /**
     * Active products in the given order (newest viewed first), without the one being viewed, at most LIMIT.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    public function __invoke(array $productIds, int $exceptProductId): Collection
    {
        $ids = array_slice(array_values(array_unique(array_filter(
            $productIds,
            fn (int $id): bool => $id > 0 && $id !== $exceptProductId,
        ))), 0, self::LIMIT);

        if ($ids === []) {
            return new Collection;
        }

        return Product::query()->active()->with(ProductListing::CARD_RELATIONS)->whereKey($ids)->get()
            ->sortBy(fn (Product $product): int|false => array_search($product->getId(), $ids, true))
            ->values();
    }
}
