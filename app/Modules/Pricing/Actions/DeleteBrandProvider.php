<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\BrandProvider;
use App\Modules\Pricing\Queries\SupplierPrices;

/**
 * Removes a supplier's commission from a brand; the brand's SKUs that buy from this supplier are re-priced.
 */
final class DeleteBrandProvider
{
    public function __invoke(BrandProvider $row, ?int $adminId = null): void
    {
        $row->delete();
        self::reprice($row, $adminId);
    }

    /**
     * Re-prices the brand's SKUs that have a row from this supplier.
     */
    public static function reprice(BrandProvider $row, ?int $adminId): void
    {
        $variantIds = array_values(array_intersect(
            app(VariantPricingInputs::class)->variantIdsOfBrand($row->getBrandId()),
            app(SupplierPrices::class)->variantIdsOfProvider($row->getProviderId()),
        ));

        RecalculateVariantPrices::dispatch($variantIds, PriceChangeReason::CommissionChanged, $adminId)->afterCommit();
    }
}
