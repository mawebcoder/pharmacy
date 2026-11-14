<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Cart\Enums\CouponUsageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One customer's use of a coupon, followed through checkout (roadmap D3.4, S20): logged when the code is applied at
 * checkout, reserved when the payment link is created (until the gateway's expiry), then used or released. A record
 * itself, so it is not written to the activity log.
 */
final class CouponUsage extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_COUPON_ID = 'coupon_id';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_DISCOUNT_AMOUNT = 'discount_amount';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_RESERVED_UNTIL = 'reserved_until';

    public const string COLUMN_USED_AT = 'used_at';

    public const string COLUMN_RELEASED_AT = 'released_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCouponId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_COUPON_ID);
    }

    public function setCouponId(int $couponId): static
    {
        return $this->setAttribute(self::COLUMN_COUPON_ID, $couponId);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_ORDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $orderId): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $orderId);
    }

    public function getDiscountAmount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DISCOUNT_AMOUNT);
    }

    public function setDiscountAmount(int $discountAmount): static
    {
        return $this->setAttribute(self::COLUMN_DISCOUNT_AMOUNT, $discountAmount);
    }

    public function getStatus(): CouponUsageStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(CouponUsageStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getReservedUntil(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_RESERVED_UNTIL);
    }

    public function setReservedUntil(?Carbon $reservedUntil): static
    {
        return $this->setAttribute(self::COLUMN_RESERVED_UNTIL, $reservedUntil);
    }

    public function getUsedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_USED_AT);
    }

    public function setUsedAt(?Carbon $usedAt): static
    {
        return $this->setAttribute(self::COLUMN_USED_AT, $usedAt);
    }

    public function getReleasedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_RELEASED_AT);
    }

    public function setReleasedAt(?Carbon $releasedAt): static
    {
        return $this->setAttribute(self::COLUMN_RELEASED_AT, $releasedAt);
    }

    /**
     * Whether this use holds one of the coupon's slots: used, or reserved and not yet expired.
     */
    public function countsTowardLimits(): bool
    {
        return $this->getStatus() === CouponUsageStatus::Used
            || ($this->getStatus() === CouponUsageStatus::Reserved && $this->getReservedUntil()?->isFuture() === true);
    }

    /**
     * Uses that hold a slot of the coupon's limits: used, or reserved and not yet expired.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCountingTowardLimits(Builder $query): Builder
    {
        return $query->where(fn (Builder $holding): Builder => $holding
            ->where(self::COLUMN_STATUS, CouponUsageStatus::Used)
            ->orWhere(fn (Builder $reserved): Builder => $reserved
                ->where(self::COLUMN_STATUS, CouponUsageStatus::Reserved)
                ->where(self::COLUMN_RESERVED_UNTIL, '>', now())));
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, self::COLUMN_COUPON_ID)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_DISCOUNT_AMOUNT => 'integer',
            self::COLUMN_STATUS => CouponUsageStatus::class,
            self::COLUMN_RESERVED_UNTIL => 'datetime',
            self::COLUMN_USED_AT => 'datetime',
            self::COLUMN_RELEASED_AT => 'datetime',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
