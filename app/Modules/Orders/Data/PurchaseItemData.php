<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use Carbon\CarbonImmutable;

/**
 * A purchase document row as the admin typed it (S30): which order item, from which supplier, how many, at what unit
 * cost (rial), and the units' expiry date for reference.
 */
final readonly class PurchaseItemData
{
    public function __construct(
        public int $orderItemId,
        public int $providerId,
        public int $quantity,
        public int $unitCost,
        public ?CarbonImmutable $expiryDate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  order_item_id, provider_id, quantity, unit_cost, expiry_date (Y-m-d)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderItemId: (int) ($data['order_item_id'] ?? 0),
            providerId: (int) ($data['provider_id'] ?? 0),
            quantity: (int) ($data['quantity'] ?? 0),
            unitCost: (int) ($data['unit_cost'] ?? 0),
            expiryDate: blank($data['expiry_date'] ?? null) ? null : CarbonImmutable::parse((string) $data['expiry_date'])->startOfDay(),
        );
    }
}
