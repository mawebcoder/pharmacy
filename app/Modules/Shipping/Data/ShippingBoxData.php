<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use App\Modules\Shipping\Models\ShippingBox;

/**
 * Input for a box: plain-text name, sizes in cm, own weight in g, Tipax's carton number when it is one.
 */
final readonly class ShippingBoxData
{
    public function __construct(
        public string $name,
        public ?int $tipaxSize,
        public int $lengthCm,
        public int $widthCm,
        public int $heightCm,
        public int $emptyWeightG,
        public int $maxWeightG,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the shipping_boxes columns (ShippingBox::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $tipaxSize = $data[ShippingBox::COLUMN_TIPAX_SIZE] ?? null;

        return new self(
            name: trim(strip_tags((string) ($data[ShippingBox::COLUMN_NAME] ?? ''))),
            tipaxSize: blank($tipaxSize) ? null : (int) $tipaxSize,
            lengthCm: (int) ($data[ShippingBox::COLUMN_LENGTH_CM] ?? 0),
            widthCm: (int) ($data[ShippingBox::COLUMN_WIDTH_CM] ?? 0),
            heightCm: (int) ($data[ShippingBox::COLUMN_HEIGHT_CM] ?? 0),
            emptyWeightG: (int) ($data[ShippingBox::COLUMN_EMPTY_WEIGHT_G] ?? 0),
            maxWeightG: (int) ($data[ShippingBox::COLUMN_MAX_WEIGHT_G] ?? 0),
            isActive: (bool) ($data[ShippingBox::COLUMN_IS_ACTIVE] ?? true),
        );
    }
}
