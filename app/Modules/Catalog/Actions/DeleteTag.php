<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Support\Facades\DB;

/**
 * Moves a tag to the trash and removes it from its products. Its slug stays reserved while it is in the trash.
 */
final class DeleteTag
{
    public function __invoke(Tag $tag): void
    {
        $productIds = $tag->products()->pluck('products.id')->all();

        DB::transaction(function () use ($tag): void {
            $tag->products()->detach();
            $tag->delete();
        });

        ProductSearchIndex::refreshWhere(Product::query()->whereKey($productIds));
    }
}
