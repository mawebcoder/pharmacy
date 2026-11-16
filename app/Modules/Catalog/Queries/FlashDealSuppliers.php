<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\FlashDeal;

/**
 * The supplier of each SKU's live flash deal (roadmap S33, owner 2026-11-17): while a deal runs it replaces the SKU's
 * default supplier (preferred SKU → preferred product → cheapest) wherever the site says who it sells from. The
 * pricing engine and the SKU's regular price are unchanged.
 */
final readonly class FlashDealSuppliers
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, int> variant id => supplier id, only SKUs with a live deal
     */
    public function forVariants(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        /** @var array<int, int> */
        return FlashDeal::query()
            ->live()
            ->whereIn(FlashDeal::COLUMN_VARIANT_ID, $variantIds)
            ->whereNotNull(FlashDeal::COLUMN_PROVIDER_ID)
            ->pluck(FlashDeal::COLUMN_PROVIDER_ID, FlashDeal::COLUMN_VARIANT_ID)
            ->map(fn (mixed $providerId): int => (int) $providerId)
            ->all();
    }
}
