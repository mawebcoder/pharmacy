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
     * An order item as OrderPackingItems gives it, with another quantity when only some units are packed (a return).
     *
     * @param  array{order_item_id: int, variant_id: int|null, title: string, quantity: int, weight_g: int|null, box_capacities: array<int, int>, unit_value: int, ship_separately: bool, liquid_or_fragile: bool, tipax_content_id: int|null}  $item
     */
    public static function fromOrderItem(array $item, ?int $quantity = null): self
    {
        return new self(
            key: $item['order_item_id'],
            variantId: $item['variant_id'],
            title: $item['title'],
            quantity: $quantity ?? $item['quantity'],
            unitWeightG: $item['weight_g'],
            boxCapacities: $item['box_capacities'],
            unitValue: $item['unit_value'],
            shipSeparately: $item['ship_separately'],
            liquidOrFragile: $item['liquid_or_fragile'],
            tipaxContentId: $item['tipax_content_id'],
        );
    }

    /**
     * The share of a box one piece takes (1 / pieces that fit), or null when the box does not take it.
     */
    public function share(BoxSpec $box): ?float
    {
        $capacity = $this->boxCapacities[$box->id] ?? 0;

        return $capacity > 0 ? 1 / $capacity : null;
    }
}
