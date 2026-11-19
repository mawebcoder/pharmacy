<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * One line to pack (roadmap S31): a key the caller knows it by (an order item id), its title, quantity, weight of one
 * piece (null when the SKU has none), how many pieces fit each box (box id => pieces; a missing box does not take it;
 * owner, 2026-11-18), one piece's value in rial, whether it ships alone or is liquid or fragile, and its product's
 * Tipax content (owner, 2026-11-19; null: the store default).
 */
final readonly class PackItem
{
    /**
     * @param  array<int, int>  $boxCapacities
     */
    public function __construct(
        public int $key,
        public ?int $variantId,
        public string $title,
        public int $quantity,
        public ?int $unitWeightG,
        public array $boxCapacities,
        public int $unitValue,
        public bool $shipSeparately = false,
        public bool $liquidOrFragile = false,
        public ?int $tipaxContentId = null,
    ) {}

    /**
     * The share of a box one piece takes (1 / pieces that fit), or null when the box does not take it.
     */
    public function share(BoxSpec $box): ?float
    {
        $capacity = $this->boxCapacities[$box->id] ?? 0;

        return $capacity > 0 ? 1 / $capacity : null;
    }
}
