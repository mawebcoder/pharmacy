<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What Tipax last said about an order's parcel (roadmap S37): written only when the order page asks Tipax, at most
 * once an hour, never by hand, so it is not audited.
 */
final class ParcelTracking extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_TRACKING_CODE = 'tracking_code';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_DATA = 'data';

    public const string COLUMN_CHECKED_AT = 'checked_at';

    public const string COLUMN_SYNCED_AT = 'synced_at';

    public function getOrderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ORDER_ID);
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $orderId);
    }

    public function getTrackingCode(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TRACKING_CODE);
    }

    public function setTrackingCode(string $code): static
    {
        return $this->setAttribute(self::COLUMN_TRACKING_CODE, $code);
    }

    public function getStatus(): ?string
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(?string $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->getAttribute(self::COLUMN_DATA);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function setData(?array $data): static
    {
        return $this->setAttribute(self::COLUMN_DATA, $data);
    }

    public function getCheckedAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_CHECKED_AT);
    }

    public function setCheckedAt(Carbon $at): static
    {
        return $this->setAttribute(self::COLUMN_CHECKED_AT, $at);
    }

    public function getSyncedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_SYNCED_AT);
    }

    public function setSyncedAt(?Carbon $at): static
    {
        return $this->setAttribute(self::COLUMN_SYNCED_AT, $at);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_DATA => 'array',
            self::COLUMN_CHECKED_AT => 'datetime',
            self::COLUMN_SYNCED_AT => 'datetime',
        ];
    }
}
