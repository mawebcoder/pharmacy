<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

/**
 * A purchase document row as the admin typed it (S30): which order item, from which supplier, how many, at what unit
 * cost (rial).
 */
final readonly class PurchaseItemData
{
    public function __construct(
        public int $orderItemId,
        public int $providerId,
        public int $quantity,
        public int $unitCost,
    ) {}

    /**
     * @param  array<string, mixed>  $data  order_item_id, provider_id, quantity, unit_cost
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderItemId: (int) ($data['order_item_id'] ?? 0),
            providerId: (int) ($data['provider_id'] ?? 0),
            quantity: (int) ($data['quantity'] ?? 0),
            unitCost: (int) ($data['unit_cost'] ?? 0),
        );
    }
}
