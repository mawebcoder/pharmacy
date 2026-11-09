<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Enums\ProviderPriceEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change of a supplier's price row of a SKU (roadmap D3.2, S14): the row's values after the change, who made it
 * and when. Never updated. A history table itself, so it is not written to the activity log again.
 */
final class ProviderPriceChange extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_COST = 'cost';

    public const string COLUMN_IS_AVAILABLE = 'is_available';

    public const string COLUMN_IS_ENABLED = 'is_enabled';

    public const string COLUMN_LEAD_TIME_HOURS = 'lead_time_hours';

    public const string COLUMN_EVENT = 'event';

    public const string COLUMN_CHANGED_BY = 'changed_by';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProviderId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PROVIDER_ID);
    }

    public function setProviderId(int $providerId): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $providerId);
    }

    public function getVariantId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VARIANT_ID);
    }

    public function setVariantId(int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $variantId);
    }

    public function getCost(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_COST);

        return $value === null ? null : (int) $value;
    }

    public function setCost(?int $cost): static
    {
        return $this->setAttribute(self::COLUMN_COST, $cost);
    }

    public function getIsAvailable(): ?bool
    {
        $value = $this->getAttribute(self::COLUMN_IS_AVAILABLE);

        return $value === null ? null : (bool) $value;
    }

    public function setIsAvailable(?bool $isAvailable): static
    {
        return $this->setAttribute(self::COLUMN_IS_AVAILABLE, $isAvailable);
    }

    public function getIsEnabled(): ?bool
    {
        $value = $this->getAttribute(self::COLUMN_IS_ENABLED);

        return $value === null ? null : (bool) $value;
    }

    public function setIsEnabled(?bool $isEnabled): static
    {
        return $this->setAttribute(self::COLUMN_IS_ENABLED, $isEnabled);
    }

    public function getLeadTimeHours(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_LEAD_TIME_HOURS);

        return $value === null ? null : (int) $value;
    }

    public function setLeadTimeHours(?int $leadTimeHours): static
    {
        return $this->setAttribute(self::COLUMN_LEAD_TIME_HOURS, $leadTimeHours);
    }

    public function getEvent(): ProviderPriceEvent
    {
        return $this->getAttribute(self::COLUMN_EVENT);
    }

    public function setEvent(ProviderPriceEvent $event): static
    {
        return $this->setAttribute(self::COLUMN_EVENT, $event);
    }

    public function getChangedBy(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CHANGED_BY);

        return $value === null ? null : (int) $value;
    }

    public function setChangedBy(?int $adminId): static
    {
        return $this->setAttribute(self::COLUMN_CHANGED_BY, $adminId);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_VARIANT_ID => 'integer',
            self::COLUMN_COST => 'integer',
            self::COLUMN_IS_AVAILABLE => 'boolean',
            self::COLUMN_IS_ENABLED => 'boolean',
            self::COLUMN_LEAD_TIME_HOURS => 'integer',
            self::COLUMN_EVENT => ProviderPriceEvent::class,
            self::COLUMN_CHANGED_BY => 'integer',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
