<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One payment attempt for an order (roadmap D7.4, S25). An order has at most one pending, unexpired attempt; its link
 * is reused until it ends. A record of the gateway exchange, so it is not in the admin audit log.
 */
final class Payment extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_GATEWAY = 'gateway';

    public const string COLUMN_AMOUNT = 'amount';

    public const string COLUMN_AUTHORITY = 'authority';

    public const string COLUMN_URL = 'url';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_EXPIRES_AT = 'expires_at';

    public const string COLUMN_REF_ID = 'ref_id';

    public const string COLUMN_CARD_PAN = 'card_pan';

    public const string COLUMN_FAILURE_REASON = 'failure_reason';

    public const string COLUMN_VERIFIED_AT = 'verified_at';

    public const string COLUMN_FAILED_AT = 'failed_at';

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

    public function getGateway(): string
    {
        return (string) $this->getAttribute(self::COLUMN_GATEWAY);
    }

    public function setGateway(string $gateway): static
    {
        return $this->setAttribute(self::COLUMN_GATEWAY, $gateway);
    }

    public function getAmount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_AMOUNT);
    }

    public function setAmount(int $amount): static
    {
        return $this->setAttribute(self::COLUMN_AMOUNT, $amount);
    }

    public function getAuthority(): string
    {
        return (string) $this->getAttribute(self::COLUMN_AUTHORITY);
    }

    public function setAuthority(string $authority): static
    {
        return $this->setAttribute(self::COLUMN_AUTHORITY, $authority);
    }

    public function getUrl(): string
    {
        return (string) $this->getAttribute(self::COLUMN_URL);
    }

    public function setUrl(string $url): static
    {
        return $this->setAttribute(self::COLUMN_URL, $url);
    }

    public function getStatus(): PaymentAttemptStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(PaymentAttemptStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getExpiresAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_EXPIRES_AT);
    }

    public function setExpiresAt(Carbon $expiresAt): static
    {
        return $this->setAttribute(self::COLUMN_EXPIRES_AT, $expiresAt);
    }

    public function getRefId(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_REF_ID);

        return $value === null ? null : (string) $value;
    }

    public function setRefId(?string $refId): static
    {
        return $this->setAttribute(self::COLUMN_REF_ID, $refId);
    }

    public function setCardPan(?string $cardPan): static
    {
        return $this->setAttribute(self::COLUMN_CARD_PAN, $cardPan);
    }

    public function getFailureReason(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_FAILURE_REASON);

        return $value === null ? null : (string) $value;
    }

    public function setFailureReason(?string $failureReason): static
    {
        return $this->setAttribute(self::COLUMN_FAILURE_REASON, $failureReason);
    }

    public function setVerifiedAt(?Carbon $verifiedAt): static
    {
        return $this->setAttribute(self::COLUMN_VERIFIED_AT, $verifiedAt);
    }

    public function setFailedAt(?Carbon $failedAt): static
    {
        return $this->setAttribute(self::COLUMN_FAILED_AT, $failedAt);
    }

    /**
     * Its link can still be used: pending and not expired.
     */
    public function isOpen(): bool
    {
        return $this->getStatus() === PaymentAttemptStatus::Pending && $this->getExpiresAt()->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(self::COLUMN_STATUS, PaymentAttemptStatus::Pending)->where(self::COLUMN_EXPIRES_AT, '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => PaymentAttemptStatus::class,
            self::COLUMN_AMOUNT => 'integer',
            self::COLUMN_EXPIRES_AT => 'datetime',
            self::COLUMN_VERIFIED_AT => 'datetime',
            self::COLUMN_FAILED_AT => 'datetime',
        ];
    }
}
