<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order (roadmap D3.4, S25), created when the customer goes to pay. It keeps copies of the address, customer,
 * coupon and shipping quote, and its items copy names and prices, so later changes never alter it. Money in rial.
 * Status changes go through ChangeOrderStatus, which logs each one.
 */
final class Order extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NUMBER = 'number';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_PAYMENT_STATUS = 'payment_status';

    public const string COLUMN_ADDRESS_ID = 'address_id';

    public const string COLUMN_ADDRESS_SNAPSHOT = 'address_snapshot';

    public const string COLUMN_CUSTOMER_SNAPSHOT = 'customer_snapshot';

    public const string COLUMN_SUBTOTAL = 'subtotal';

    public const string COLUMN_DISCOUNT_TOTAL = 'discount_total';

    public const string COLUMN_SHIPPING_TOTAL = 'shipping_total';

    public const string COLUMN_TOTAL = 'total';

    public const string COLUMN_COUPON_ID = 'coupon_id';

    public const string COLUMN_COUPON_CODE = 'coupon_code';

    public const string COLUMN_COUPON_SNAPSHOT = 'coupon_snapshot';

    public const string COLUMN_SHIPPING_METHOD = 'shipping_method';

    public const string COLUMN_SHIPPING_QUOTE_SNAPSHOT = 'shipping_quote_snapshot';

    public const string COLUMN_CUSTOMER_NOTE = 'customer_note';

    public const string COLUMN_ADMIN_NOTE = 'admin_note';

    public const string COLUMN_PLACED_AT = 'placed_at';

    public const string COLUMN_EXPIRES_AT = 'expires_at';

    public const string COLUMN_PAID_AT = 'paid_at';

    public const string COLUMN_CANCELLED_AT = 'cancelled_at';

    public const string COLUMN_DELIVERED_AT = 'delivered_at';

    /**
     * The carrier's tracking code, typed by the admin when the order goes on its way (S29; Tipax fills it from S27).
     */
    public const string COLUMN_TRACKING_CODE = 'tracking_code';

    public const string COLUMN_REFUNDED_AT = 'refunded_at';

    /**
     * The bank or gateway panel reference of a manual refund (S29).
     */
    public const string COLUMN_REFUND_REFERENCE = 'refund_reference';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getNumber(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NUMBER);

        return $value === null ? null : (string) $value;
    }

    public function setNumber(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_NUMBER, $value);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CUSTOMER_ID);
    }

    public function setCustomerId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_ID, $value);
    }

    public function getStatus(): OrderStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(OrderStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $value);
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->getAttribute(self::COLUMN_PAYMENT_STATUS);
    }

    public function setPaymentStatus(PaymentStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_PAYMENT_STATUS, $value);
    }

    public function getAddressId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_ADDRESS_ID);

        return $value === null ? null : (int) $value;
    }

    public function setAddressId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_ADDRESS_ID, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAddressSnapshot(): array
    {
        return $this->getAttribute(self::COLUMN_ADDRESS_SNAPSHOT);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function setAddressSnapshot(array $value): static
    {
        return $this->setAttribute(self::COLUMN_ADDRESS_SNAPSHOT, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerSnapshot(): array
    {
        return $this->getAttribute(self::COLUMN_CUSTOMER_SNAPSHOT);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function setCustomerSnapshot(array $value): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_SNAPSHOT, $value);
    }

    public function getSubtotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SUBTOTAL);
    }

    public function setSubtotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SUBTOTAL, $value);
    }

    public function getDiscountTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DISCOUNT_TOTAL);
    }

    public function setDiscountTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_DISCOUNT_TOTAL, $value);
    }

    public function getShippingTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SHIPPING_TOTAL);
    }

    public function setShippingTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_TOTAL, $value);
    }

    public function getTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_TOTAL);
    }

    public function setTotal(int $value): static
    {
        return $this->setAttribute(self::COLUMN_TOTAL, $value);
    }

    public function getCouponId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_COUPON_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCouponId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_ID, $value);
    }

    public function getCouponCode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_COUPON_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setCouponCode(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_CODE, $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCouponSnapshot(): ?array
    {
        return $this->getAttribute(self::COLUMN_COUPON_SNAPSHOT);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function setCouponSnapshot(?array $value): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_SNAPSHOT, $value);
    }

    public function getShippingMethod(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SHIPPING_METHOD);
    }

    public function setShippingMethod(string $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_METHOD, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShippingQuoteSnapshot(): array
    {
        return $this->getAttribute(self::COLUMN_SHIPPING_QUOTE_SNAPSHOT);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function setShippingQuoteSnapshot(array $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_QUOTE_SNAPSHOT, $value);
    }

    public function getCustomerNote(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_CUSTOMER_NOTE);

        return $value === null ? null : (string) $value;
    }

    public function setCustomerNote(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_NOTE, $value);
    }

    public function getAdminNote(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_ADMIN_NOTE);

        return $value === null ? null : (string) $value;
    }

    public function setAdminNote(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_ADMIN_NOTE, $value);
    }

    public function getPlacedAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_PLACED_AT);
    }

    public function setPlacedAt(Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_PLACED_AT, $value);
    }

    public function getExpiresAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_EXPIRES_AT);
    }

    public function setExpiresAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_EXPIRES_AT, $value);
    }

    public function getPaidAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_PAID_AT);
    }

    public function setPaidAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_PAID_AT, $value);
    }

    public function getCancelledAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CANCELLED_AT);
    }

    public function setCancelledAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_CANCELLED_AT, $value);
    }

    public function getDeliveredAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_DELIVERED_AT);
    }

    public function setDeliveredAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_DELIVERED_AT, $value);
    }

    public function getTrackingCode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_TRACKING_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setTrackingCode(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_TRACKING_CODE, $value);
    }

    public function getRefundedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_REFUNDED_AT);
    }

    public function setRefundedAt(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_REFUNDED_AT, $value);
    }

    public function getRefundReference(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_REFUND_REFERENCE);

        return $value === null ? null : (string) $value;
    }

    public function setRefundReference(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_REFUND_REFERENCE, $value);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy(OrderItem::COLUMN_ID);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy(OrderStatusLog::COLUMN_ID);
    }

    /**
     * Still waiting for its payment and not expired.
     */
    public function canBePaid(): bool
    {
        return $this->getStatus() === OrderStatus::Pending && $this->getExpiresAt()?->isFuture() === true;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, int $customerId): Builder
    {
        return $query->where(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    public function activityLogLabel(): string
    {
        return 'سفارش: '.($this->getNumber() ?? '#'.$this->getId());
    }

    /**
     * Audit trail of admin edits (status changes are in order_status_logs).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('orders')
            ->logOnly([self::COLUMN_PAYMENT_STATUS, self::COLUMN_TRACKING_CODE, self::COLUMN_REFUND_REFERENCE, self::COLUMN_ADMIN_NOTE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => OrderStatus::class,
            self::COLUMN_PAYMENT_STATUS => PaymentStatus::class,
            self::COLUMN_ADDRESS_SNAPSHOT => 'array',
            self::COLUMN_CUSTOMER_SNAPSHOT => 'array',
            self::COLUMN_COUPON_SNAPSHOT => 'array',
            self::COLUMN_SHIPPING_QUOTE_SNAPSHOT => 'array',
            self::COLUMN_SUBTOTAL => 'integer',
            self::COLUMN_DISCOUNT_TOTAL => 'integer',
            self::COLUMN_SHIPPING_TOTAL => 'integer',
            self::COLUMN_TOTAL => 'integer',
            self::COLUMN_PLACED_AT => 'datetime',
            self::COLUMN_EXPIRES_AT => 'datetime',
            self::COLUMN_PAID_AT => 'datetime',
            self::COLUMN_CANCELLED_AT => 'datetime',
            self::COLUMN_DELIVERED_AT => 'datetime',
            self::COLUMN_REFUNDED_AT => 'datetime',
        ];
    }
}
