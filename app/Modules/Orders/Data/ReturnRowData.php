<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Enums\ReturnReason;

/**
 * One row of a new return (roadmap S27, owner 2026-11-19): units of an order item and why they come back. An item may
 * be on several rows with different reasons.
 */
final readonly class ReturnRowData
{
    public function __construct(
        public int $orderItemId,
        public int $quantity,
        public ReturnReason $reason,
        public ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row  a form row: order_item_id, quantity, reason, note
     */
    public static function fromArray(array $row): self
    {
        $note = trim((string) ($row['note'] ?? ''));

        return new self(
            orderItemId: (int) ($row['order_item_id'] ?? 0),
            quantity: (int) ($row['quantity'] ?? 0),
            reason: ReturnReason::tryFrom((string) ($row['reason'] ?? '')) ?? ReturnReason::Other,
            note: $note === '' ? null : $note,
        );
    }

    /**
     * Units per order item over the rows.
     *
     * @param  list<self>  $rows
     * @return array<int, int>
     */
    public static function quantities(array $rows): array
    {
        $quantities = [];

        foreach ($rows as $row) {
            $quantities[$row->orderItemId] = ($quantities[$row->orderItemId] ?? 0) + $row->quantity;
        }

        return $quantities;
    }
}
