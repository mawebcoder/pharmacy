<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\FlashDealFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A flash deal («پیشنهاد لحظه‌ای», roadmap S28): a SKU at a deal price between two times, optionally for at most so many
 * units. Live while active, inside its window and not sold out; then SyncFlashDealPrices puts its price on the SKU.
 * Units are reserved when an order is placed and released when it expires or is cancelled (owner, 2026-11-16).
 */
final class FlashDeal extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<FlashDealFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_DEAL_PRICE = 'deal_price';

    public const string COLUMN_STARTS_AT = 'starts_at';

    public const string COLUMN_ENDS_AT = 'ends_at';

    public const string COLUMN_QUANTITY_LIMIT = 'quantity_limit';

    public const string COLUMN_SOLD_COUNT = 'sold_count';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getVariantId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VARIANT_ID);
    }

    public function setVariantId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $value);
    }

    /**
     * The supplier the deal sells from; null: the SKU's usual supplier (owner, 2026-11-17).
     */
    public function getProviderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setProviderId(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $value);
    }

    public function getDealPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DEAL_PRICE);
    }

    public function setDealPrice(int $value): static
    {
        return $this->setAttribute(self::COLUMN_DEAL_PRICE, $value);
    }

    public function getStartsAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_STARTS_AT);
    }

    public function setStartsAt(Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_STARTS_AT, $value);
    }

    public function getEndsAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_ENDS_AT);
    }

    public function setEndsAt(Carbon $value): static
    {
        return $this->setAttribute(self::COLUMN_ENDS_AT, $value);
    }

    public function getQuantityLimit(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_QUANTITY_LIMIT);

        return $value === null ? null : (int) $value;
    }

    public function setQuantityLimit(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_QUANTITY_LIMIT, $value);
    }

    public function getSoldCount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SOLD_COUNT);
    }

    public function setSoldCount(int $value): static
    {
        return $this->setAttribute(self::COLUMN_SOLD_COUNT, $value);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function setIsActive(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $value);
    }

    public function isSoldOut(): bool
    {
        return $this->getQuantityLimit() !== null && $this->getSoldCount() >= $this->getQuantityLimit();
    }

    /**
     * Units still to sell; null without a limit.
     */
    public function unitsLeft(): ?int
    {
        return $this->getQuantityLimit() === null ? null : max(0, $this->getQuantityLimit() - $this->getSoldCount());
    }

    /**
     * Active, inside its window and not sold out.
     */
    public function isLive(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->isActive() && $this->getStartsAt()->lessThanOrEqualTo($at) && $this->getEndsAt()->greaterThan($at) && ! $this->isSoldOut();
    }

    /**
     * Active deals inside their window and not sold out.
     *
     * @param  Builder<FlashDeal>  $query
     * @return Builder<FlashDeal>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where(self::COLUMN_IS_ACTIVE, true)
            ->where(self::COLUMN_STARTS_AT, '<=', now())
            ->where(self::COLUMN_ENDS_AT, '>', now())
            ->where(fn (Builder $limit): Builder => $limit->whereNull(self::COLUMN_QUANTITY_LIMIT)->orWhereColumn(self::COLUMN_SOLD_COUNT, '<', self::COLUMN_QUANTITY_LIMIT));
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->getRelationValue('variant');
    }

    public function activityLogLabel(): string
    {
        return 'پیشنهاد لحظه‌ای: '.($this->getVariant()?->getSku() ?? '#'.$this->getId());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_VARIANT_ID, self::COLUMN_PROVIDER_ID, self::COLUMN_DEAL_PRICE, self::COLUMN_STARTS_AT, self::COLUMN_ENDS_AT, self::COLUMN_QUANTITY_LIMIT, self::COLUMN_IS_ACTIVE])
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
            self::COLUMN_DEAL_PRICE => 'integer',
            self::COLUMN_STARTS_AT => 'datetime',
            self::COLUMN_ENDS_AT => 'datetime',
            self::COLUMN_QUANTITY_LIMIT => 'integer',
            self::COLUMN_SOLD_COUNT => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
