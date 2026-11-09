<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Search\ProductSearchIndex;

/**
 * Takes a product out of a collection.
 */
final class RemoveProductFromCollection
{
    public function __invoke(ProductCollection $collection, Product $product): void
    {
        $collection->products()->detach($product->getId());

        ProductSearchIndex::refresh($product->unsetRelation('collections'));
    }
}
