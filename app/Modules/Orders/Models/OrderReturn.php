<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\ReturnScope;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order return («مرجوعی», roadmap S27, owner 2026-11-19): the units of the order's items coming back (OrderReturnItem),
 * the refund for them (what the customer paid per unit; the shipping fee on the return that completes a full one),
 * and Tipax's price, boxes and tracking codes for the way back. Written only by the return actions.
 */
final class OrderReturn extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_SEQUENCE = 'sequence';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_IS_FULL = 'is_full';

    public const string COLUMN_REASON = 'reason';

    public const string COLUMN_ITEMS_REFUND = 'items_refund';

    public const string COLUMN_SHIPPING_FEE_REFUND = 'shipping_fee_refund';

    public const string COLUMN_RETURN_SHIPPING_PRICE = 'return_shipping_price';

    public const string COLUMN_RETURN_SERVICE_ID = 'return_service_id';

    public const string COLUMN_RETURN_SERVICE_TITLE = 'return_service_title';

    public const string COLUMN_PARCELS = 'parcels';

    public const string COLUMN_TRACKING_CODES = 'tracking_codes';

    public const string COLUMN_CREATED_BY = 'created_by';

    public const string COLUMN_APPROVED_BY = 'approved_by';

    public const string COLUMN_APPROVED_AT = 'approved_at';

    public const string COLUMN_CANCELLED_BY = 'cancelled_by';

    public const string COLUMN_CANCELLED_AT = 'cancelled_at';

    public const string COLUMN_CANCEL_REASON = 'cancel_reason';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getOrderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ID);
    }

    public function setOrderId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $value);
    }

    public function getSequence(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SEQUENCE);
    }

    public function setSequence(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SEQUENCE, $value);
    }

    public function getStatus(): OrderReturnStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS) ?? OrderReturnStatus::Draft;
    }

    public function setStatus(OrderReturnStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $value);
    }

    public function isFull(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_FULL);
    }

    public function setIsFull(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_FULL, $value);
    }

    public function getScope(): ReturnScope
    {
        return $this->isFull() ? ReturnScope::Full : ReturnScope::Partial;
    }

    public function getReason(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_REASON);

        return $value === null ? null : (string) $value;
    }

    public function setReason(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_REASON, $value);
    }

    public function getItemsRefund(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ITEMS_REFUND);
    }

    public function setItemsRefund(int $value): static
    {
        return $this->setAttribute(self::COLUMN_ITEMS_REFUND, $value);
    }

    public function getShippingFeeRefund(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SHIPPING_FEE_REFUND);
    }

    public function setShippingFeeRefund(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_FEE_REFUND, $value);
    }

    /**
     * What the customer gets back: the returned units' paid amount, plus the shipping fee when this return completes a
     * full one. Tipax's return price is paid by the store and not taken off (owner, 2026-11-19).
     */
    public function getRefundTotal(): int
    {
        return $this->getItemsRefund() + $this->getShippingFeeRefund();
    }

    public function getReturnShippingPrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_RETURN_SHIPPING_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setReturnShippingPrice(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_RETURN_SHIPPING_PRICE, $value);
    }

    public function getReturnServiceId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_RETURN_SERVICE_ID);

        return $value === null ? null : (int) $value;
    }

    public function setReturnServiceId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_RETURN_SERVICE_ID, $value);
    }

    public function getReturnServiceTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_RETURN_SERVICE_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setReturnServiceTitle(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_RETURN_SERVICE_TITLE, $value);
    }

    /**
     * @return list<array{box: string|null, weight_g: int, value: int}>
     */
    public function getParcels(): array
    {
        return $this->getAttribute(self::COLUMN_PARCELS) ?? [];
    }

    /**
     * @param  list<array{box: string|null, weight_g: int, value: int}>  $value
     */
    public function setParcels(array $value): static
    {
        return $this->setAttribute(self::COLUMN_PARCELS, $value);
    }

    /**
     * @return list<string>
     */
    public function getTrackingCodes(): array
    {
        return $this->getAttribute(self::COLUMN_TRACKING_CODES) ?? [];
    }

    /**
     * @param  list<string>|null  $value
     */
    public function setTrackingCodes(?array $value): static
    {
        return $this->setAttribute(self::COLUMN_TRACKING_CODES, $value);
    }

    public function getCreatedBy(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CREATED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setCreatedBy(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_CREATED_BY, $value);
    }

    public function setApprovedBy(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_APPROVED_BY, $value);
    }

    public function getApprovedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_APPROVED_AT);
    }

    public function setApprovedAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_APPROVED_AT, $value);
    }

    public function setCancelledBy(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_CANCELLED_BY, $value);
    }

    public function getCancelledAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CANCELLED_AT);
    }

    public function setCancelledAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_CANCELLED_AT, $value);
    }

    public function getCancelReason(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_CANCEL_REASON);

        return $value === null ? null : (string) $value;
    }

    public function setCancelReason(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_CANCEL_REASON, $value);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * The order's number and «-R» with the return's place: PH-140508-000123-R2. Also its trace code at Tipax.
     */
    public function getNumber(): string
    {
        return ($this->order?->getNumber() ?? (string) $this->getOrderId()).'-R'.$this->getSequence();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getOrder(): Order
    {
        /** @var Order */
        return $this->order;
    }

    /**
     * @return HasMany<OrderReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class)->orderBy(OrderReturnItem::COLUMN_ID);
    }

    public function activityLogLabel(): string
    {
        return 'مرجوعی: '.$this->getNumber();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly([self::COLUMN_STATUS, self::COLUMN_CANCEL_REASON])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => OrderReturnStatus::class,
            self::COLUMN_IS_FULL => 'boolean',
            self::COLUMN_ITEMS_REFUND => 'integer',
            self::COLUMN_SHIPPING_FEE_REFUND => 'integer',
            self::COLUMN_RETURN_SHIPPING_PRICE => 'integer',
            self::COLUMN_PARCELS => 'array',
            self::COLUMN_TRACKING_CODES => 'array',
            self::COLUMN_APPROVED_AT => 'datetime',
            self::COLUMN_CANCELLED_AT => 'datetime',
        ];
    }
}
