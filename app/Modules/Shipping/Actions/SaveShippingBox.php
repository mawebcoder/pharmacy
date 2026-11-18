<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Data\ShippingBoxData;
use App\Modules\Shipping\Models\ShippingBox;

/**
 * Adds or changes a box (roadmap S31). Boxes are listed smallest first, so the sort is the volume. Boxes are switched
 * off rather than deleted: packed orders keep their box as it was.
 */
final class SaveShippingBox
{
    #[\NoDiscard]
    public function __invoke(ShippingBoxData $data, ?ShippingBox $box = null): ShippingBox
    {
        $box ??= new ShippingBox;
        $box->setName($data->name)
            ->setTipaxSize($data->tipaxSize)
            ->setLengthCm($data->lengthCm)
            ->setWidthCm($data->widthCm)
            ->setHeightCm($data->heightCm)
            ->setEmptyWeightG($data->emptyWeightG)
            ->setMaxWeightG($data->maxWeightG)
            ->setSort($data->lengthCm * $data->widthCm * $data->heightCm)
            ->setIsActive($data->isActive)
            ->save();

        return $box;
    }
}
