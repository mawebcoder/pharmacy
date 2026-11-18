<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Models\ShippingBox;

/**
 * The active boxes, for the packer (roadmap S31).
 */
final readonly class ActiveBoxes
{
    /**
     * @return list<BoxSpec>
     */
    public function specs(): array
    {
        return array_values(ShippingBox::query()
            ->active()
            ->orderBy(ShippingBox::COLUMN_SORT)
            ->get()
            ->map(fn (ShippingBox $box): BoxSpec => new BoxSpec(
                id: $box->getId(),
                name: $box->getName(),
                lengthCm: $box->getLengthCm(),
                widthCm: $box->getWidthCm(),
                heightCm: $box->getHeightCm(),
                emptyWeightG: $box->getEmptyWeightG(),
                maxWeightG: $box->getMaxWeightG(),
                tipaxSize: $box->getTipaxSize(),
            ))
            ->all());
    }
}
