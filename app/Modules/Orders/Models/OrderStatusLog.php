<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One change of an order's status (roadmap D3.4 order_status_logs): never updated. A log itself, not audited.
 */
final class OrderStatusLog extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_FROM_STATUS = 'from_status';

    public const string COLUMN_TO_STATUS = 'to_status';

    public const string COLUMN_ACTOR_TYPE = 'actor_type';

    public const string COLUMN_ACTOR_ID = 'actor_id';

    public const string COLUMN_NOTE = 'note';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

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

    public function getFromStatus(): ?OrderStatus
    {
        return $this->getAttribute(self::COLUMN_FROM_STATUS);
    }

    public function setFromStatus(?OrderStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_FROM_STATUS, $value);
    }

    public function getToStatus(): OrderStatus
    {
        return $this->getAttribute(self::COLUMN_TO_STATUS);
    }

    public function setToStatus(OrderStatus $value): static
    {
        return $this->setAttribute(self::COLUMN_TO_STATUS, $value);
    }

    public function getActorType(): OrderActor
    {
        return $this->getAttribute(self::COLUMN_ACTOR_TYPE);
    }

    public function setActorType(OrderActor $value): static
    {
        return $this->setAttribute(self::COLUMN_ACTOR_TYPE, $value);
    }

    public function getActorId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_ACTOR_ID);

        return $value === null ? null : (int) $value;
    }

    public function setActorId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_ACTOR_ID, $value);
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

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_FROM_STATUS => OrderStatus::class,
            self::COLUMN_TO_STATUS => OrderStatus::class,
            self::COLUMN_ACTOR_TYPE => OrderActor::class,
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
