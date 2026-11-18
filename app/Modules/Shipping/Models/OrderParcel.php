<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shipping\Enums\ParcelContent;
use Illuminate\Database\Eloquent\Model;

/**
 * One parcel of a paid order (roadmap S31, owner 2026-11-17): the box as it was when packed (null when no box takes
 * the item), the items in it, their weight and the parcel's total, the declared value (rial) and the Tipax item type.
 * Written only by PackOrder.
 */
final class OrderParcel extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_SEQUENCE = 'sequence';

    public const string COLUMN_BOX_ID = 'box_id';

    public const string COLUMN_BOX_SNAPSHOT = 'box_snapshot';

    public const string COLUMN_ITEMS = 'items';

    public const string COLUMN_ITEMS_WEIGHT_G = 'items_weight_g';

    public const string COLUMN_TOTAL_WEIGHT_G = 'total_weight_g';

    public const string COLUMN_DECLARED_VALUE = 'declared_value';

    public const string COLUMN_CONTENT_TYPE = 'content_type';

    public const string COLUMN_IS_ESTIMATE = 'is_estimate';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getOrderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ID);
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $orderId);
    }

    public function getSequence(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SEQUENCE);
    }

    public function setSequence(int $sequence): static
    {
        return $this->setAttribute(self::COLUMN_SEQUENCE, $sequence);
    }

    public function getBoxId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_BOX_ID);

        return $value === null ? null : (int) $value;
    }

    public function setBoxId(?int $boxId): static
    {
        return $this->setAttribute(self::COLUMN_BOX_ID, $boxId);
    }

    /**
     * @return array{name: string, length_cm: int, width_cm: int, height_cm: int, empty_weight_g: int, tipax_size: int|null}|null
     */
    public function getBoxSnapshot(): ?array
    {
        /** @var array{name: string, length_cm: int, width_cm: int, height_cm: int, empty_weight_g: int, tipax_size: int|null}|null $value */
        $value = $this->getAttribute(self::COLUMN_BOX_SNAPSHOT);

        return $value;
    }

    /**
     * @param  array{name: string, length_cm: int, width_cm: int, height_cm: int, empty_weight_g: int, tipax_size: int|null}|null  $snapshot
     */
    public function setBoxSnapshot(?array $snapshot): static
    {
        return $this->setAttribute(self::COLUMN_BOX_SNAPSHOT, $snapshot);
    }

    /**
     * @return list<array{order_item_id: int, variant_id: int|null, title: string, quantity: int}>
     */
    public function getItems(): array
    {
        /** @var list<array{order_item_id: int, variant_id: int|null, title: string, quantity: int}> $value */
        $value = $this->getAttribute(self::COLUMN_ITEMS) ?? [];

        return $value;
    }

    /**
     * @param  list<array{order_item_id: int, variant_id: int|null, title: string, quantity: int}>  $items
     */
    public function setItems(array $items): static
    {
        return $this->setAttribute(self::COLUMN_ITEMS, $items);
    }

    public function getItemsWeightG(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ITEMS_WEIGHT_G);
    }

    public function setItemsWeightG(int $weight): static
    {
        return $this->setAttribute(self::COLUMN_ITEMS_WEIGHT_G, $weight);
    }

    public function getTotalWeightG(): int
    {
        return (int) $this->getAttribute(self::COLUMN_TOTAL_WEIGHT_G);
    }

    public function setTotalWeightG(int $weight): static
    {
        return $this->setAttribute(self::COLUMN_TOTAL_WEIGHT_G, $weight);
    }

    public function getDeclaredValue(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DECLARED_VALUE);
    }

    public function setDeclaredValue(int $value): static
    {
        return $this->setAttribute(self::COLUMN_DECLARED_VALUE, $value);
    }

    public function getContentType(): ParcelContent
    {
        return $this->getAttribute(self::COLUMN_CONTENT_TYPE);
    }

    public function setContentType(ParcelContent $type): static
    {
        return $this->setAttribute(self::COLUMN_CONTENT_TYPE, $type);
    }

    public function isEstimate(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ESTIMATE);
    }

    public function setIsEstimate(bool $isEstimate): static
    {
        return $this->setAttribute(self::COLUMN_IS_ESTIMATE, $isEstimate);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_BOX_SNAPSHOT => 'array',
            self::COLUMN_ITEMS => 'array',
            self::COLUMN_CONTENT_TYPE => ParcelContent::class,
            self::COLUMN_IS_ESTIMATE => 'boolean',
        ];
    }
}
