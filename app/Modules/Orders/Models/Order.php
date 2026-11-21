<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An order (roadmap D3.4, S23), created when the customer goes to pay. It keeps copies of the address, customer,
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

    public const string COLUMN_SOURCING_STATUS = 'sourcing_status';

    public const string COLUMN_ADDRESS_ID = 'address_id';

    public const string COLUMN_ADDRESS_SNAPSHOT = 'address_snapshot';

    public const string COLUMN_CUSTOMER_SNAPSHOT = 'customer_snapshot';

    public const string COLUMN_SUBTOTAL = 'subtotal';

    public const string COLUMN_DISCOUNT_TOTAL = 'discount_total';

    public const string COLUMN_SHIPPING_PRICE = 'shipping_price';

    public const string COLUMN_SHIPPING_DISCOUNT = 'shipping_discount';

    public const string COLUMN_SHIPPING_DISCOUNT_REASON = 'shipping_discount_reason';

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

    public const string COLUMN_DIFFERENCE_DUE = 'difference_due';

    public const string COLUMN_DIFFERENCE_DUE_UNTIL = 'difference_due_until';

    public const string COLUMN_SOURCE = 'source';

    public const string COLUMN_CREATED_BY_ADMIN_ID = 'created_by_admin_id';

    public const string COLUMN_MANUAL_DISCOUNT = 'manual_discount';

    public const string COLUMN_MANUAL_DISCOUNT_REASON = 'manual_discount_reason';

    public const string COLUMN_PAID_AT = 'paid_at';

    public const string COLUMN_CANCELLED_AT = 'cancelled_at';

    public const string COLUMN_DELIVERED_AT = 'delivered_at';

    /**
     * The carrier's tracking code, typed by the admin when the order goes on its way (S24; Tipax fills it from S35).
     */
    public const string COLUMN_TRACKING_CODE = 'tracking_code';

    public const string COLUMN_REFUNDED_AT = 'refunded_at';

    /**
     * The bank or gateway panel reference of a manual refund (S24).
     */
    public const string COLUMN_REFUND_REFERENCE = 'refund_reference';

    public const string COLUMN_REFUND_ITEMS_AMOUNT = 'refund_items_amount';

    public const string COLUMN_REFUND_SHIPPING_AMOUNT = 'refund_shipping_amount';

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

    /**
     * How far its goods have been bought (S25, admin only); null while it has no purchase document.
     */
    public function getSourcingStatus(): ?SourcingStatus
    {
        return $this->getAttribute(self::COLUMN_SOURCING_STATUS);
    }

    public function setSourcingStatus(?SourcingStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_SOURCING_STATUS, $value);
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

    /**
     * The shipping price before any discount (owner, 2026-11-16); shipping_total is this less shipping_discount.
     */
    public function getShippingPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SHIPPING_PRICE);
    }

    public function setShippingPrice(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_PRICE, $value);
    }

    public function getShippingDiscount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SHIPPING_DISCOUNT);
    }

    public function setShippingDiscount(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_DISCOUNT, $value);
    }

    /**
     * Why shipping was discounted: «coupon» (a free-shipping coupon) or «threshold» (the order reached the free-shipping
     * amount); null when it was not.
     */
    public function getShippingDiscountReason(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SHIPPING_DISCOUNT_REASON);

        return $value === null ? null : (string) $value;
    }

    public function setShippingDiscountReason(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIPPING_DISCOUNT_REASON, $value);
    }

    /**
     * The free shipping the store paid for on an order that was delivered and then cancelled (owner, 2026-11-16): it
     * is held back from the product refund. Nothing before delivery: nothing was shipped.
     */
    public function getDeliveredFreeShipping(): int
    {
        return $this->getDeliveredAt() === null ? 0 : $this->getShippingDiscount();
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

    /**
     * The order's date as shown to customers and admins (owner, 2026-11-16): when it was paid, or when it was placed
     * while still unpaid.
     */
    public function getOrderDate(): Carbon
    {
        return $this->getPaidAt() ?? $this->getPlacedAt();
    }

    public function setPlacedAt(Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_PLACED_AT, $value);
    }

    public function getExpiresAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_EXPIRES_AT);
    }

    /**
     * What the customer still owes after staff raised a paid order's total (owner, 2026-11-19), in rial; 0 when nothing.
     */
    public function getDifferenceDue(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DIFFERENCE_DUE);
    }

    public function setDifferenceDue(int $value): static
    {
        return $this->setAttribute(self::COLUMN_DIFFERENCE_DUE, $value);
    }

    /**
     * Until when the difference's payment link is open (one hour; staff open it again).
     */
    public function getDifferenceDueUntil(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_DIFFERENCE_DUE_UNTIL);
    }

    public function setDifferenceDueUntil(?Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_DIFFERENCE_DUE_UNTIL, $value);
    }

    /**
     * The customer can pay the difference now: owed, its link still open, and the order not gone out.
     */
    public function canPayDifference(): bool
    {
        return $this->getDifferenceDue() > 0
            && $this->getDifferenceDueUntil()?->isFuture() === true
            && in_array($this->getStatus(), [OrderStatus::Paid, OrderStatus::Processing], true);
    }

    /**
     * Placed by the customer on the site, or by staff in the panel (owner, 2026-11-19).
     */
    public function getSource(): OrderSource
    {
        return $this->getAttribute(self::COLUMN_SOURCE) ?? OrderSource::Site;
    }

    public function setSource(OrderSource $value): static
    {
        return $this->setAttribute(self::COLUMN_SOURCE, $value);
    }

    /**
     * The admin who placed it in the panel; null for site orders.
     */
    public function getCreatedByAdminId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CREATED_BY_ADMIN_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCreatedByAdminId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_CREATED_BY_ADMIN_ID, $value);
    }

    /**
     * The discount staff typed in for a panel order (rial), part of discount_total and shared over its items.
     */
    public function getManualDiscount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MANUAL_DISCOUNT);
    }

    public function setManualDiscount(int $value): static
    {
        return $this->setAttribute(self::COLUMN_MANUAL_DISCOUNT, $value);
    }

    public function getManualDiscountReason(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_MANUAL_DISCOUNT_REASON);

        return $value === null ? null : (string) $value;
    }

    public function setManualDiscountReason(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_MANUAL_DISCOUNT_REASON, $value);
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

    public function getRefundItemsAmount(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_REFUND_ITEMS_AMOUNT);

        return $value === null ? null : (int) $value;
    }

    public function setRefundItemsAmount(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_REFUND_ITEMS_AMOUNT, $value);
    }

    public function getRefundShippingAmount(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_REFUND_SHIPPING_AMOUNT);

        return $value === null ? null : (int) $value;
    }

    public function setRefundShippingAmount(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_REFUND_SHIPPING_AMOUNT, $value);
    }

    /**
     * What the customer paid for the products: the subtotal after the coupon (the items' paid totals add up to it).
     */
    public function getItemsPaidTotal(): int
    {
        return $this->getSubtotal() - $this->getDiscountTotal();
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
     * Its returns («مرجوعی‌ها», S27), oldest first.
     *
     * @return HasMany<OrderReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->orderBy(OrderReturn::COLUMN_SEQUENCE);
    }

    /**
     * Its purchase document (S25), once an admin opened one.
     *
     * @return HasOne<OrderPurchase, $this>
     */
    public function purchase(): HasOne
    {
        return $this->hasOne(OrderPurchase::class);
    }

    public function getPurchase(): ?OrderPurchase
    {
        return $this->purchase;
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
            ->logOnly([self::COLUMN_PAYMENT_STATUS, self::COLUMN_TRACKING_CODE, self::COLUMN_REFUND_REFERENCE, self::COLUMN_REFUND_ITEMS_AMOUNT, self::COLUMN_REFUND_SHIPPING_AMOUNT, self::COLUMN_ADMIN_NOTE])
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
            self::COLUMN_SOURCE => OrderSource::class,
            self::COLUMN_MANUAL_DISCOUNT => 'integer',
            self::COLUMN_DIFFERENCE_DUE => 'integer',
            self::COLUMN_DIFFERENCE_DUE_UNTIL => 'datetime',
            self::COLUMN_SOURCING_STATUS => SourcingStatus::class,
            self::COLUMN_ADDRESS_SNAPSHOT => 'array',
            self::COLUMN_CUSTOMER_SNAPSHOT => 'array',
            self::COLUMN_COUPON_SNAPSHOT => 'array',
            self::COLUMN_SHIPPING_QUOTE_SNAPSHOT => 'array',
            self::COLUMN_SUBTOTAL => 'integer',
            self::COLUMN_DISCOUNT_TOTAL => 'integer',
            self::COLUMN_SHIPPING_PRICE => 'integer',
            self::COLUMN_SHIPPING_DISCOUNT => 'integer',
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
