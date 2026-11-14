<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Database\Factories\VariantPriceFactory;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\ProviderChoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A SKU price record (roadmap D3.2, S15): the printed price, the supplier and cost used, the commission and where it
 * came from, the selling price and the haraj at one moment. Never updated; the latest one is the SKU's price
 * (product_variants.current_price_id) and order items keep the id they were sold at. Money in Rial. A history table
 * itself, so it is not written to the activity log again.
 */
final class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_OFFICIAL_PRICE = 'official_price';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_PROVIDER_COST = 'provider_cost';

    public const string COLUMN_PROVIDER_CHOICE = 'provider_choice';

    public const string COLUMN_COMMISSION_PERCENT = 'commission_percent';

    public const string COLUMN_COMMISSION_SOURCE = 'commission_source';

    public const string COLUMN_PRICE = 'price';

    public const string COLUMN_SALE_PRICE = 'sale_price';

    public const string COLUMN_REASON = 'reason';

    public const string COLUMN_FLAGS = 'flags';

    public const string COLUMN_CHANGED_BY = 'changed_by';

    public const string COLUMN_CREATED_AT = 'created_at';

    /**
     * The selling price would be above the printed price, so the printed price is used (less margin than the
     * commission).
     */
    public const string FLAG_CAPPED_BY_PRINTED = 'capped_by_printed';

    /**
     * No active supplier, so the SKU is sold at its printed price.
     */
    public const string FLAG_SOLD_AT_PRINTED = 'sold_at_printed';

    /**
     * The haraj was no longer below the new selling price and was removed.
     */
    public const string FLAG_HARAJ_REMOVED = 'haraj_removed';

    /**
     * The selling price moved more than 15 % from the previous record.
     */
    public const string FLAG_CHANGE_OVER_15_PERCENT = 'change_over_15_percent';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getVariantId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VARIANT_ID);
    }

    public function setVariantId(int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $variantId);
    }

    public function getOfficialPrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_OFFICIAL_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setOfficialPrice(?int $officialPrice): static
    {
        return $this->setAttribute(self::COLUMN_OFFICIAL_PRICE, $officialPrice);
    }

    public function getProviderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setProviderId(?int $providerId): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_ID, $providerId);
    }

    public function getProviderCost(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_COST);

        return $value === null ? null : (int) $value;
    }

    public function setProviderCost(?int $providerCost): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_COST, $providerCost);
    }

    public function getProviderChoice(): ?ProviderChoice
    {
        return $this->getAttribute(self::COLUMN_PROVIDER_CHOICE);
    }

    public function setProviderChoice(?ProviderChoice $choice): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_CHOICE, $choice);
    }

    public function getCommissionPercent(): ?float
    {
        $value = $this->getAttribute(self::COLUMN_COMMISSION_PERCENT);

        return $value === null ? null : (float) $value;
    }

    public function setCommissionPercent(?float $commissionPercent): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_PERCENT, $commissionPercent);
    }

    public function getCommissionSource(): ?CommissionSource
    {
        return $this->getAttribute(self::COLUMN_COMMISSION_SOURCE);
    }

    public function setCommissionSource(?CommissionSource $source): static
    {
        return $this->setAttribute(self::COLUMN_COMMISSION_SOURCE, $source);
    }

    public function getPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRICE);
    }

    public function setPrice(int $price): static
    {
        return $this->setAttribute(self::COLUMN_PRICE, $price);
    }

    public function getSalePrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_SALE_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setSalePrice(?int $salePrice): static
    {
        return $this->setAttribute(self::COLUMN_SALE_PRICE, $salePrice);
    }

    public function getReason(): PriceChangeReason
    {
        return $this->getAttribute(self::COLUMN_REASON);
    }

    public function setReason(PriceChangeReason $reason): static
    {
        return $this->setAttribute(self::COLUMN_REASON, $reason);
    }

    /**
     * @return list<string> self::FLAG_*
     */
    public function getFlags(): array
    {
        /** @var list<string> $flags */
        $flags = $this->getAttribute(self::COLUMN_FLAGS) ?? [];

        return $flags;
    }

    /**
     * @param  list<string>  $flags
     */
    public function setFlags(array $flags): static
    {
        return $this->setAttribute(self::COLUMN_FLAGS, $flags === [] ? null : $flags);
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->getFlags(), true);
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
            self::COLUMN_VARIANT_ID => 'integer',
            self::COLUMN_OFFICIAL_PRICE => 'integer',
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_PROVIDER_COST => 'integer',
            self::COLUMN_PROVIDER_CHOICE => ProviderChoice::class,
            self::COLUMN_COMMISSION_PERCENT => 'decimal:2',
            self::COLUMN_COMMISSION_SOURCE => CommissionSource::class,
            self::COLUMN_PRICE => 'integer',
            self::COLUMN_SALE_PRICE => 'integer',
            self::COLUMN_REASON => PriceChangeReason::class,
            self::COLUMN_FLAGS => 'array',
            self::COLUMN_CHANGED_BY => 'integer',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
