<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An order registered with Tipax, going out or coming back (roadmap S35, owner 2026-11-19). Written by the
 * registration and Tipax's callbacks, never by hand, so it is not audited.
 */
final class TipaxShipment extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_KIND = 'kind';

    public const string COLUMN_TRACE_CODE = 'trace_code';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_CORRELATION_ID = 'correlation_id';

    public const string COLUMN_TRACKING_CODES = 'tracking_codes';

    public const string COLUMN_REQUEST = 'request';

    public const string COLUMN_RESPONSE = 'response';

    public const string COLUMN_CALLBACK = 'callback';

    public const string COLUMN_FAILURE_REASON = 'failure_reason';

    public const string COLUMN_REGISTERED_AT = 'registered_at';

    public function getOrderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ID);
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $orderId);
    }

    public function getKind(): TipaxShipmentKind
    {
        return $this->getAttribute(self::COLUMN_KIND);
    }

    public function setKind(TipaxShipmentKind $kind): static
    {
        return $this->setAttribute(self::COLUMN_KIND, $kind);
    }

    /**
     * Our reference at Tipax: the order number, with «-R1», «-R2» … for returns (S27).
     */
    public function getTraceCode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_TRACE_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setTraceCode(string $value): static
    {
        return $this->setAttribute(self::COLUMN_TRACE_CODE, $value);
    }

    public function getStatus(): TipaxShipmentStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(TipaxShipmentStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getCorrelationId(): ?string
    {
        return $this->getAttribute(self::COLUMN_CORRELATION_ID);
    }

    public function setCorrelationId(?string $id): static
    {
        return $this->setAttribute(self::COLUMN_CORRELATION_ID, $id);
    }

    /**
     * @return list<string>
     */
    public function getTrackingCodes(): array
    {
        return array_values((array) ($this->getAttribute(self::COLUMN_TRACKING_CODES) ?? []));
    }

    /**
     * @param  list<string>  $codes
     */
    public function setTrackingCodes(array $codes): static
    {
        return $this->setAttribute(self::COLUMN_TRACKING_CODES, $codes);
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    public function setRequest(?array $request): static
    {
        return $this->setAttribute(self::COLUMN_REQUEST, $request);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function setResponse(?array $response): static
    {
        return $this->setAttribute(self::COLUMN_RESPONSE, $response);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCallback(): ?array
    {
        return $this->getAttribute(self::COLUMN_CALLBACK);
    }

    /**
     * @param  array<string, mixed>|null  $callback
     */
    public function setCallback(?array $callback): static
    {
        return $this->setAttribute(self::COLUMN_CALLBACK, $callback);
    }

    public function getFailureReason(): ?string
    {
        return $this->getAttribute(self::COLUMN_FAILURE_REASON);
    }

    public function setFailureReason(?string $reason): static
    {
        return $this->setAttribute(self::COLUMN_FAILURE_REASON, $reason === null ? null : mb_substr($reason, 0, 500));
    }

    public function getRegisteredAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_REGISTERED_AT);
    }

    public function setRegisteredAt(?Carbon $at): static
    {
        return $this->setAttribute(self::COLUMN_REGISTERED_AT, $at);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_KIND => TipaxShipmentKind::class,
            self::COLUMN_STATUS => TipaxShipmentStatus::class,
            self::COLUMN_TRACKING_CODES => 'array',
            self::COLUMN_REQUEST => 'array',
            self::COLUMN_RESPONSE => 'array',
            self::COLUMN_CALLBACK => 'array',
            self::COLUMN_REGISTERED_AT => 'datetime',
        ];
    }
}
