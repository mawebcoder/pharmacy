<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One row of an order's purchase document (roadmap S30, owner 2026-11-16), typed by an admin: this many units of an
 * order item came from this supplier at this unit cost (rial), with how many arrived and how many are missing. An
 * order item may be split over several rows; their quantities never exceed the item's.
 */
final class OrderPurchaseItem extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_PURCHASE_ID = 'order_purchase_id';

    public const string COLUMN_ORDER_ITEM_ID = 'order_item_id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_PROVIDER_NAME = 'provider_name';

    public const string COLUMN_QUANTITY = 'quantity';

    public const string COLUMN_UNIT_COST = 'unit_cost';

    public const string COLUMN_RECEIVED_QUANTITY = 'received_quantity';

    public const string COLUMN_MISSING_QUANTITY = 'missing_quantity';

    public const string COLUMN_MISSING_NOTE = 'missing_note';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getOrderPurchaseId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_PURCHASE_ID);
    }

    public function setOrderPurchaseId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_PURCHASE_ID, $value);
    }

    public function getOrderItemId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ITEM_ID, $value);
    }

    public function getProviderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setProviderId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $value);
    }

    public function getProviderName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_PROVIDER_NAME);
    }

    public function setProviderName(string $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_NAME, $value);
    }

    public function getQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_QUANTITY);
    }

    public function setQuantity(int $value): static
    {
        return $this->setAttribute(self::COLUMN_QUANTITY, $value);
    }

    public function getUnitCost(): int
    {
        return (int) $this->getAttribute(self::COLUMN_UNIT_COST);
    }

    public function setUnitCost(int $value): static
    {
        return $this->setAttribute(self::COLUMN_UNIT_COST, $value);
    }

    public function getReceivedQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_RECEIVED_QUANTITY);
    }

    public function setReceivedQuantity(int $value): static
    {
        return $this->setAttribute(self::COLUMN_RECEIVED_QUANTITY, $value);
    }

    public function getMissingQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MISSING_QUANTITY);
    }

    public function setMissingQuantity(int $value): static
    {
        return $this->setAttribute(self::COLUMN_MISSING_QUANTITY, $value);
    }

    public function getMissingNote(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_MISSING_NOTE);

        return $value === null ? null : (string) $value;
    }

    public function setMissingNote(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_MISSING_NOTE, $value);
    }

    /**
     * quantity × unit cost (rial).
     */
    public function getTotalCost(): int
    {
        return $this->getQuantity() * $this->getUnitCost();
    }

    /**
     * Units neither received nor marked missing yet.
     */
    public function getOpenQuantity(): int
    {
        return max(0, $this->getQuantity() - $this->getReceivedQuantity() - $this->getMissingQuantity());
    }

    /**
     * @return BelongsTo<OrderPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(OrderPurchase::class, self::COLUMN_ORDER_PURCHASE_ID);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function getOrderItem(): OrderItem
    {
        /** @var OrderItem */
        return $this->orderItem;
    }

    public function activityLogLabel(): string
    {
        return 'ردیف سند خرید: '.$this->getOrderItem()->getProductName().' · '.$this->getProviderName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly([self::COLUMN_PROVIDER_NAME, self::COLUMN_QUANTITY, self::COLUMN_UNIT_COST, self::COLUMN_RECEIVED_QUANTITY, self::COLUMN_MISSING_QUANTITY, self::COLUMN_MISSING_NOTE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_QUANTITY => 'integer',
            self::COLUMN_UNIT_COST => 'integer',
            self::COLUMN_RECEIVED_QUANTITY => 'integer',
            self::COLUMN_MISSING_QUANTITY => 'integer',
        ];
    }
}
