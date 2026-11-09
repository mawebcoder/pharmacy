<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Modules\Pricing\Models\ProviderPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Suppliers' prices of SKUs (roadmap D3.2): the active supplier of each SKU is the cheapest row that is enabled,
 * available and whose supplier is active. Our costs: for the admin and the pricing engine only.
 */
final readonly class SupplierPrices
{
    /**
     * Every supplier row of a SKU with its supplier, cheapest first.
     *
     * @return Collection<int, ProviderPrice>
     */
    public function of(int $variantId): Collection
    {
        return ProviderPrice::query()
            ->with('provider')
            ->where(ProviderPrice::COLUMN_VARIANT_ID, $variantId)
            ->orderBy(ProviderPrice::COLUMN_COST)
            ->orderBy(ProviderPrice::COLUMN_ID)
            ->get();
    }

    /**
     * The active supplier row of each SKU, or null when none can be sold from.
     *
     * @param  list<int>  $variantIds
     * @return array<int, ProviderPrice|null> keyed by variant id
     */
    public function activeFor(array $variantIds): array
    {
        $active = array_fill_keys($variantIds, null);

        $rows = ProviderPrice::query()
            ->with('provider')
            ->usable()
            ->whereIn(ProviderPrice::COLUMN_VARIANT_ID, $variantIds)
            ->orderBy(ProviderPrice::COLUMN_COST)
            ->orderBy(ProviderPrice::COLUMN_ID)
            ->get();

        foreach ($rows as $row) {
            $active[$row->getVariantId()] ??= $row;
        }

        return $active;
    }

    /**
     * The active supplier's name of each SKU that has one, for admin tables.
     *
     * @param  list<int>  $variantIds
     * @return array<int, string> variant id => supplier name
     */
    public function activeNames(array $variantIds): array
    {
        $names = [];

        foreach ($this->activeFor($variantIds) as $variantId => $price) {
            $name = $price?->getProvider()?->getName();

            if ($name !== null) {
                $names[$variantId] = $name;
            }
        }

        return $names;
    }

    /**
     * Which of these SKUs have at least one supplier row (their stock follows the suppliers).
     *
     * @param  list<int>  $variantIds
     * @return array<int, bool> keyed by variant id
     */
    public function hasSuppliers(array $variantIds): array
    {
        $with = array_flip(array_map(intval(...), ProviderPrice::query()
            ->whereIn(ProviderPrice::COLUMN_VARIANT_ID, $variantIds)
            ->distinct()
            ->pluck(ProviderPrice::COLUMN_VARIANT_ID)
            ->all()));

        return array_map(fn (int $variantId): bool => isset($with[$variantId]), array_combine($variantIds, $variantIds));
    }

    /**
     * Ids of SKUs that have supplier rows, as a subquery (e.g. to list SKUs without a supplier).
     */
    public function variantIdsWithSuppliers(): QueryBuilder
    {
        return DB::table('provider_prices')->select(ProviderPrice::COLUMN_VARIANT_ID);
    }

    /**
     * SKUs with a row of this supplier.
     *
     * @return list<int>
     */
    public function variantIdsOfProvider(int $providerId): array
    {
        return array_values(array_map(intval(...), ProviderPrice::query()
            ->where(ProviderPrice::COLUMN_PROVIDER_ID, $providerId)
            ->pluck(ProviderPrice::COLUMN_VARIANT_ID)
            ->all()));
    }
}
