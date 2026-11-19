<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use App\Modules\Shipping\Enums\ParcelContent;

/**
 * One parcel the packer made (roadmap S31): its box (null when no box takes the item), the items as key => quantity
 * with titles, the items' weight, the total with the box, the declared value (rial), what it holds for Tipax, and
 * whether defaults stood in for a missing weight or size type, and the Tipax content its items share (null when they
 * differ or have none).
 */
final readonly class PackedParcel
{
    /**
     * @param  list<array{key: int, variant_id: int|null, title: string, quantity: int}>  $items
     */
    public function __construct(
        public ?BoxSpec $box,
        public array $items,
        public int $itemsWeightG,
        public int $totalWeightG,
        public int $declaredValue,
        public ParcelContent $content,
        public bool $isEstimate,
        public ?int $tipaxContentId = null,
    ) {}
}
