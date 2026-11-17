<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;

/**
 * Product counts for other modules (roadmap S35 trust counters).
 */
final readonly class ProductCounts
{
    /**
     * Products for sale, as in search (Product::scopeSellable).
     */
    public function sellable(): int
    {
        return Product::query()->sellable()->count();
    }
}
