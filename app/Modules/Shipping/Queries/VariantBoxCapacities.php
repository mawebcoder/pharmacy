<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Models\ShippingBox;
use App\Modules\Shipping\Models\VariantBoxCapacity;

/**
 * How many pieces of SKUs fit each box (roadmap S31, owner 2026-11-18), for the SKU form, the order snapshot and the
 * «بدون وزن یا جا در کارتن» list.
 */
final readonly class VariantBoxCapacities
{
    /**
     * Box id => pieces for each SKU asked for.
     *
     * @param  list<int>  $variantIds
     * @return array<int, array<int, int>>
     */
    public function for(array $variantIds): array
    {
        $capacities = [];

        foreach (VariantBoxCapacity::query()->whereIn(VariantBoxCapacity::COLUMN_VARIANT_ID, $variantIds)->get() as $row) {
            $capacities[$row->getVariantId()][$row->getBoxId()] = $row->getCapacity();
        }

        return $capacities;
    }

    /**
     * The SKUs that fit at least one box.
     *
     * @return list<int>
     */
    public function variantIdsWithAnyBox(): array
    {
        return array_values(array_map(intval(...), VariantBoxCapacity::query()->distinct()->pluck(VariantBoxCapacity::COLUMN_VARIANT_ID)->all()));
    }

    /**
     * The available boxes for the SKU form: id => «name (L × W × H)», smallest first.
     *
     * @return array<int, string>
     */
    public function boxOptions(): array
    {
        return ShippingBox::query()
            ->active()
            ->orderBy(ShippingBox::COLUMN_SORT)
            ->get()
            ->mapWithKeys(fn (ShippingBox $box): array => [$box->getId() => $box->getName().' ('.$box->sizesLabel().')'])
            ->all();
    }
}
