<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * An active box for the packer (roadmap S31): its sizes, own weight, the most a parcel in it may weigh (box included),
 * and its volume, which orders the boxes smallest first.
 */
final readonly class BoxSpec
{
    public function __construct(
        public int $id,
        public string $name,
        public int $lengthCm,
        public int $widthCm,
        public int $heightCm,
        public int $emptyWeightG,
        public int $maxWeightG,
        public ?int $tipaxSize,
    ) {}

    public function volume(): int
    {
        return $this->lengthCm * $this->widthCm * $this->heightCm;
    }
}
