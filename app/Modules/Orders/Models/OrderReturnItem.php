<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Units of one order item in a return (roadmap S27, owner 2026-11-19): how many, what the customer paid per unit
 * (copied from the order item) and the refund for them (OrderReturnRefunds).
 */
final class OrderReturnItem extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_RETURN_ID = 'order_return_id';

    public const string COLUMN_ORDER_ITEM_ID = 'order_item_id';

    public const string COLUMN_QUANTITY = 'quantity';

    public const string COLUMN_PAID_UNIT = 'paid_unit';

    public const string COLUMN_REFUND_AMOUNT = 'refund_amount';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getOrderReturnId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_RETURN_ID);
    }

    public function setOrderReturnId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_RETURN_ID, $value);
    }

    public function getOrderItemId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ITEM_ID, $value);
    }

    public function getQuantity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_QUANTITY);
    }

    public function setQuantity(int $value): static
    {
        return $this->setAttribute(self::COLUMN_QUANTITY, $value);
    }

    public function getPaidUnit(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PAID_UNIT);
    }

    public function setPaidUnit(int $value): static
    {
        return $this->setAttribute(self::COLUMN_PAID_UNIT, $value);
    }

    public function getRefundAmount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_REFUND_AMOUNT);
    }

    public function setRefundAmount(int $value): static
    {
        return $this->setAttribute(self::COLUMN_REFUND_AMOUNT, $value);
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
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
        return 'کالای مرجوعی #'.$this->getId();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly([self::COLUMN_QUANTITY])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_QUANTITY => 'integer',
            self::COLUMN_PAID_UNIT => 'integer',
            self::COLUMN_REFUND_AMOUNT => 'integer',
        ];
    }
}
