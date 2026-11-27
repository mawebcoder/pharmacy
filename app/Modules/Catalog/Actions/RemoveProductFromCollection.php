<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;

/**
 * Takes a product out of a collection.
 */
final class RemoveProductFromCollection
{
    public function __invoke(ProductCollection $collection, Product $product): void
    {
        $collection->products()->detach($product->getId());

        ProductsSearchFieldsChanged::dispatch([$product->getId()]);
    }
}
