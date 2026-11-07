<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\DB;

/**
 * Saves the admin's order of a product's variants (drag and drop). The order decides which SKU stands in when the
 * default is out of stock, so the product's search document is refreshed. Ids of other products are ignored.
 */
final class ReorderProductVariants
{
    /**
     * @param  list<int>  $variantIds  in the new order
     */
    public function __invoke(Product $product, array $variantIds): void
    {
        $positions = array_flip($variantIds);

        DB::transaction(function () use ($product, $positions): void {
            foreach ($product->variants()->whereKey(array_keys($positions))->get() as $variant) {
                $variant->setSort($positions[$variant->getId()] + 1)->save();
            }
        });

        ProductSearchIndex::refresh($product->unsetRelation('variants'));
    }
}
