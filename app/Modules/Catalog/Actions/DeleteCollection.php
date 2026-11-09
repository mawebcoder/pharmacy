<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CollectionChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\DB;

/**
 * Moves a collection to the trash and takes its products out of it. The system collection is refused.
 */
final class DeleteCollection
{
    /**
     * @throws CollectionChangeRefused
     */
    public function __invoke(ProductCollection $collection): void
    {
        if ($collection->isSystem()) {
            throw CollectionChangeRefused::systemCollection($collection->getNameFa());
        }

        $productIds = $collection->products()->pluck('products.id')->all();

        DB::transaction(function () use ($collection): void {
            $collection->products()->detach();
            $collection->delete();
        });

        ProductSearchIndex::refreshWhere(Product::query()->whereKey($productIds));
    }
}
