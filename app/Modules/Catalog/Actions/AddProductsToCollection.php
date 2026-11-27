<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Models\ProductCollection;

/**
 * Adds products to the end of a collection, in the given order; products already in it keep their place.
 */
final class AddProductsToCollection
{
    /**
     * @param  list<int>  $productIds
     */
    public function __invoke(ProductCollection $collection, array $productIds): void
    {
        $present = $collection->products()->pluck('products.id')->map(intval(...))->all();
        $next = (int) $collection->products()->max('collection_product.'.ProductCollection::PIVOT_SORT) + 1;
        $new = array_values(array_diff(array_unique($productIds), $present));

        foreach ($new as $offset => $productId) {
            $collection->products()->attach($productId, [ProductCollection::PIVOT_SORT => $next + $offset]);
        }

        ProductsSearchFieldsChanged::dispatch($new);
    }
}
