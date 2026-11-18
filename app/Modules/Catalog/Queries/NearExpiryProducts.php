<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

/**
 * «زودمصرف» products for the /deals row (roadmap S28): their default SKU's selling supplier has near-expiry stock
 * (owner, 2026-11-17), sellable and in stock, newest first.
 */
final readonly class NearExpiryProducts
{
    public const int ROW_LIMIT = 6;

    /**
     * @return Collection<int, Product>
     */
    public function row(int $limit = self::ROW_LIMIT): Collection
    {
        return Product::query()
            ->active()
            ->whereHas('variants', fn ($variants) => $variants->where(ProductVariant::COLUMN_IS_NEAR_EXPIRY, true))
            ->with(ProductListing::CARD_RELATIONS)
            ->latest(Product::COLUMN_UPDATED_AT)
            ->limit($limit * 4)
            ->get()
            ->filter(fn (Product $product): bool => $product->isSellableType() && ($product->getDefaultVariant()?->isInStock() ?? false) && $product->isNearExpiry())
            ->take($limit)
            ->values();
    }
}
