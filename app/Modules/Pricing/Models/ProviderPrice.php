<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Database\Factories\ProviderPriceFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What one supplier charges us for one SKU («قیمت خرید از تأمین‌کننده», roadmap D3.2, S14): cost in Rial, whether
 * the supplier has the SKU now («موجود نزد تأمین‌کننده»), whether the admin sells from it («فروش از این
 * تأمین‌کننده»), and how many hours it needs to deliver. Our cost: never shown on the storefront or sent to
 * Meilisearch. variant_id is a Catalog product_variants id (read as a foreign key only, roadmap C3 rule 1).
 */
final class ProviderPrice extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ProviderPriceFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_COST = 'cost';

    public const string COLUMN_IS_AVAILABLE = 'is_available';

    public const string COLUMN_IS_ENABLED = 'is_enabled';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public const string COLUMN_IS_PREFERRED = 'is_preferred';

    public const string COLUMN_LEAD_TIME_HOURS = 'lead_time_hours';

    public const string COLUMN_UPDATED_AT = 'updated_at';

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

    /**
     * Rial.
     */
    public function getCost(): int
    {
        return (int) $this->getAttribute(self::COLUMN_COST);
    }

    public function setCost(int $cost): static
    {
        return $this->setAttribute(self::COLUMN_COST, $cost);
    }

    public function isAvailable(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_AVAILABLE);
    }

    public function getIsAvailable(): bool
    {
        return $this->isAvailable();
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        return $this->setAttribute(self::COLUMN_IS_AVAILABLE, $isAvailable);
    }

    /**
     * Commission for this SKU from this supplier (level 1, S15b); null uses the product or brand levels.
     */
    public function getCommissionPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setCommissionPercent(?float $value): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $value);
    }

    /**
     * The admin's preferred supplier for this SKU (one per SKU): the website sells from it while it can.
     */
    public function isPreferred(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_PREFERRED);
    }

    public function getIsPreferred(): bool
    {
        return $this->isPreferred();
    }

    public function setIsPreferred(bool $isPreferred): static
    {
        return $this->setAttribute(self::COLUMN_IS_PREFERRED, $isPreferred);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ENABLED);
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled();
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        return $this->setAttribute(self::COLUMN_IS_ENABLED, $isEnabled);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
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
     * Prices we can sell from now: the admin sells from this supplier, it has the SKU, and the supplier is active.
     *
     * @param  Builder<ProviderPrice>  $query
     * @return Builder<ProviderPrice>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ENABLED, true)
            ->where(self::COLUMN_IS_AVAILABLE, true)
            ->whereHas('provider', fn (Builder $providers) => $providers->where(Provider::COLUMN_IS_ACTIVE, true));
    }

    public function activityLogLabel(): string
    {
        return 'قیمت خرید از '.($this->getProvider()?->getName() ?? 'تأمین‌کننده').' برای SKU #'.$this->getVariantId();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly([self::COLUMN_PROVIDER_ID, self::COLUMN_VARIANT_ID, self::COLUMN_COST, self::COLUMN_IS_AVAILABLE, self::COLUMN_IS_ENABLED, self::COLUMN_COMMISSION_PERCENT, self::COLUMN_IS_PREFERRED, self::COLUMN_LEAD_TIME_HOURS])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_VARIANT_ID => 'integer',
            self::COLUMN_COST => 'integer',
            self::COLUMN_IS_AVAILABLE => 'boolean',
            self::COLUMN_IS_ENABLED => 'boolean',
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
            self::COLUMN_IS_PREFERRED => 'boolean',
            self::COLUMN_LEAD_TIME_HOURS => 'integer',
        ];
    }
}
