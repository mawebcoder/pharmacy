<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderPurchaseFactory;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order's purchase document («سند خرید», roadmap S25, owner 2026-11-16): one per order, opened by an admin, whose
 * rows (OrderPurchaseItem) say which supplier provided how many units of each order item and at what cost. The
 * system never fills it. Its final margin is what the customer paid for the items less the rows' cost.
 */
final class OrderPurchase extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<OrderPurchaseFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Rows may be added, changed or removed for this many hours after the document is registered (owner, 2026-11-16).
     */
    public const int EDIT_WINDOW_HOURS = 48;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_TOTAL_COST = 'total_cost';

    public const string COLUMN_NOTE = 'note';

    public const string COLUMN_OPENED_BY = 'opened_by';

    public const string COLUMN_REGISTERED_AT = 'registered_at';

    public const string COLUMN_RECEIVED_AT = 'received_at';

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

    public function getStatus(): PurchaseStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS) ?? PurchaseStatus::Draft;
    }

    public function setStatus(PurchaseStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $value);
    }

    public function getTotalCost(): int
    {
        return (int) $this->getAttribute(self::COLUMN_TOTAL_COST);
    }

    public function setTotalCost(int $value): static
    {
        return $this->setAttribute(self::COLUMN_TOTAL_COST, $value);
    }

    public function getNote(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NOTE);

        return $value === null ? null : (string) $value;
    }

    public function setNote(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_NOTE, $value);
    }

    public function getOpenedBy(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_OPENED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setOpenedBy(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_OPENED_BY, $value);
    }

    public function getRegisteredAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_REGISTERED_AT);
    }

    public function setRegisteredAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_REGISTERED_AT, $value);
    }

    public function getReceivedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_RECEIVED_AT);
    }

    public function setReceivedAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_RECEIVED_AT, $value);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * When its rows stop being editable: 48 hours after it was registered; null while it is a draft.
     */
    public function getEditableUntil(): ?Carbon
    {
        return $this->getRegisteredAt()?->copy()->addHours(self::EDIT_WINDOW_HOURS);
    }

    /**
     * Its rows can no longer be added, changed or removed (receiving is still recorded).
     */
    public function isLocked(): bool
    {
        $until = $this->getEditableUntil();

        return $until !== null && now()->greaterThanOrEqualTo($until);
    }

    /**
     * «PO-» and the order's number: PO-PH-140508-000123.
     */
    public function getNumber(): string
    {
        return 'PO-'.($this->order?->getNumber() ?? $this->getOrderId());
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
     * @return HasMany<OrderPurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderPurchaseItem::class)->orderBy(OrderPurchaseItem::COLUMN_ID);
    }

    public function activityLogLabel(): string
    {
        return 'سند خرید: '.$this->getNumber();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly([self::COLUMN_STATUS, self::COLUMN_NOTE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => PurchaseStatus::class,
            self::COLUMN_TOTAL_COST => 'integer',
            self::COLUMN_REGISTERED_AT => 'datetime',
            self::COLUMN_RECEIVED_AT => 'datetime',
        ];
    }
}
