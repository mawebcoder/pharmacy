<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brings a product back from the trash with the variants that were deleted with it. It comes back hidden, so an
 * admin reviews it before it is for sale again.
 */
final class RestoreProduct
{
    public function __invoke(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $deletedAt = $product->getDeletedAt();

            $product->restore();

            if ($deletedAt instanceof Carbon) {
                ProductVariant::onlyTrashed()
                    ->where(ProductVariant::COLUMN_PRODUCT_ID, $product->getId())
                    ->where(ProductVariant::COLUMN_DELETED_AT, '>=', $deletedAt->copy()->subMinute())
                    ->get()
                    ->each(fn (ProductVariant $variant): bool => $variant->restore());
            }

            if ($product->isActive()) {
                $product->setStatus(ProductStatus::Hidden)->save();
            }
        });
    }
}
