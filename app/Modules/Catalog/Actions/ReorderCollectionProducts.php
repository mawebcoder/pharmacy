<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the admin's order of a collection's products (drag and drop). Ids not in the collection are ignored. The
 * order is not part of the search index, so no refresh is needed.
 */
final class ReorderCollectionProducts
{
    /**
     * @param  list<int>  $productIds  in the new order
     */
    public function __invoke(ProductCollection $collection, array $productIds): void
    {
        DB::transaction(function () use ($collection, $productIds): void {
            foreach ($productIds as $position => $productId) {
                $collection->products()->updateExistingPivot($productId, [ProductCollection::PIVOT_SORT => $position + 1]);
            }
        });
    }
}
