<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Models\VariantBoxCapacity;
use Illuminate\Support\Facades\DB;

/**
 * Saves how many pieces of a SKU fit each box (roadmap S31, owner 2026-11-18), from the SKU form. An empty or zero
 * count removes the row: the SKU does not fit that box. Boxes not in the list are left as they are.
 */
final class SaveVariantBoxCapacities
{
    /**
     * @param  array<int, int|null>  $capacities  box id => pieces
     */
    public function __invoke(int $variantId, array $capacities): void
    {
        DB::transaction(function () use ($variantId, $capacities): void {
            foreach ($capacities as $boxId => $count) {
                $row = VariantBoxCapacity::query()
                    ->where(VariantBoxCapacity::COLUMN_VARIANT_ID, $variantId)
                    ->where(VariantBoxCapacity::COLUMN_BOX_ID, $boxId)
                    ->first();

                if ($count === null || $count <= 0) {
                    $row?->delete();

                    continue;
                }

                ($row ?? (new VariantBoxCapacity)->setVariantId($variantId)->setBoxId($boxId))->setCapacity($count)->save();
            }
        });
    }
}
