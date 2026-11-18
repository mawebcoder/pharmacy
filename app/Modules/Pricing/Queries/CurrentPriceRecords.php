<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Modules\Pricing\Data\PriceRecordData;
use App\Modules\Pricing\Models\VariantPrice;

/**
 * The latest price record of each SKU (roadmap D3.2), for orders to copy when they are placed (S23).
 */
final readonly class CurrentPriceRecords
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, PriceRecordData> variant id => its latest record (SKUs without a record are left out)
     */
    public function for(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $latestIds = VariantPrice::query()
            ->selectRaw('max(id) as id')
            ->whereIn(VariantPrice::COLUMN_VARIANT_ID, array_values(array_unique($variantIds)))
            ->groupBy(VariantPrice::COLUMN_VARIANT_ID)
            ->pluck('id');

        $records = [];

        foreach (VariantPrice::query()->with('provider')->whereKey($latestIds)->get() as $price) {
            $records[$price->getVariantId()] = new PriceRecordData(
                variantPriceId: $price->getId(),
                providerId: $price->getProviderId(),
                providerName: $price->getProvider()?->getName(),
                providerCost: $price->getProviderCost(),
                providerChoice: $price->getProviderChoice()?->value,
                commissionPercent: $price->getCommissionPercent(),
                commissionSource: $price->getCommissionSource()?->value,
                officialPrice: $price->getOfficialPrice(),
                listPrice: $price->getPrice(),
                salePrice: $price->getSalePrice(),
                isNearExpiry: $price->isNearExpiry(),
                expiryDate: $price->getExpiryDate(),
            );
        }

        return $records;
    }
}
