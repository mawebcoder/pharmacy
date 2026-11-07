<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Moves a product and its variants to the trash. Orders keep their own copies of names and prices (roadmap D0
 * rule 5), so a deleted product never changes past orders.
 */
final class DeleteProduct
{
    public function __invoke(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->variants()->get()->each(fn (ProductVariant $variant): ?bool => $variant->delete());
            $product->delete();
        });
    }
}
