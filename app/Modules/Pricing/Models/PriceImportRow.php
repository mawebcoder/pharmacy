<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checked row of a supplier price import (roadmap D13.9): the matched supplier and SKU, the new values next to
 * the current ones, the selling price the new cost would give with the commission, or why it cannot be imported.
 * Money in Rial. A working table of the import, so it is not written to the activity log.
 */
final class PriceImportRow extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_PRICE_IMPORT_ID = 'price_import_id';

    public const string COLUMN_ROW_NUMBER = 'row_number';

    public const string COLUMN_PROVIDER_ID = 'provider_id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_MATCHED_BY = 'matched_by';

    public const string COLUMN_COST = 'cost';

    public const string COLUMN_IS_AVAILABLE = 'is_available';

    public const string COLUMN_IS_ENABLED = 'is_enabled';

    public const string COLUMN_LEAD_TIME_HOURS = 'lead_time_hours';

    public const string COLUMN_OLD_COST = 'old_cost';

    public const string COLUMN_OLD_IS_AVAILABLE = 'old_is_available';

    public const string COLUMN_OLD_IS_ENABLED = 'old_is_enabled';

    public const string COLUMN_SELLING_PRICE = 'selling_price';

    public const string COLUMN_IS_NEW = 'is_new';

    public const string COLUMN_CHANGES = 'changes';

    public const string COLUMN_ERROR = 'error';

    public const string COLUMN_RAW = 'raw';

    public const string MATCHED_BY_SKU = 'sku';

    public const string MATCHED_BY_BARCODE = 'barcode';

    public $timestamps = false;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getPriceImportId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRICE_IMPORT_ID);
    }

    public function setPriceImportId(int $id): static
    {
        return $this->setAttribute(self::COLUMN_PRICE_IMPORT_ID, $id);
    }

    public function getRowNumber(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ROW_NUMBER);
    }

    public function setRowNumber(int $rowNumber): static
    {
        return $this->setAttribute(self::COLUMN_ROW_NUMBER, $rowNumber);
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

    public function getVariantId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_VARIANT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setVariantId(?int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $variantId);
    }

    public function getMatchedBy(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_MATCHED_BY);

        return $value === null ? null : (string) $value;
    }

    public function setMatchedBy(?string $matchedBy): static
    {
        return $this->setAttribute(self::COLUMN_MATCHED_BY, $matchedBy);
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

    public function getOldCost(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_OLD_COST);

        return $value === null ? null : (int) $value;
    }

    public function setOldCost(?int $oldCost): static
    {
        return $this->setAttribute(self::COLUMN_OLD_COST, $oldCost);
    }

    public function getOldIsAvailable(): ?bool
    {
        $value = $this->getAttribute(self::COLUMN_OLD_IS_AVAILABLE);

        return $value === null ? null : (bool) $value;
    }

    public function setOldIsAvailable(?bool $value): static
    {
        return $this->setAttribute(self::COLUMN_OLD_IS_AVAILABLE, $value);
    }

    public function getOldIsEnabled(): ?bool
    {
        $value = $this->getAttribute(self::COLUMN_OLD_IS_ENABLED);

        return $value === null ? null : (bool) $value;
    }

    public function setOldIsEnabled(?bool $value): static
    {
        return $this->setAttribute(self::COLUMN_OLD_IS_ENABLED, $value);
    }

    /**
     * The selling price this cost would give with the SKU's commission (capped by the printed price).
     */
    public function getSellingPrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_SELLING_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setSellingPrice(?int $sellingPrice): static
    {
        return $this->setAttribute(self::COLUMN_SELLING_PRICE, $sellingPrice);
    }

    public function isNew(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_NEW);
    }

    public function setIsNew(bool $isNew): static
    {
        return $this->setAttribute(self::COLUMN_IS_NEW, $isNew);
    }

    /**
     * Whether applying it changes anything (a new row, or a different cost, switch or lead time).
     */
    public function changes(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_CHANGES);
    }

    public function setChanges(bool $changes): static
    {
        return $this->setAttribute(self::COLUMN_CHANGES, $changes);
    }

    public function getError(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_ERROR);

        return $value === null ? null : (string) $value;
    }

    public function setError(?string $error): static
    {
        return $this->setAttribute(self::COLUMN_ERROR, $error);
    }

    /**
     * The row's cells as read, for the error report.
     *
     * @return list<string>
     */
    public function getRaw(): array
    {
        /** @var list<string> $raw */
        $raw = $this->getAttribute(self::COLUMN_RAW) ?? [];

        return $raw;
    }

    /**
     * @param  list<string>  $raw
     */
    public function setRaw(array $raw): static
    {
        return $this->setAttribute(self::COLUMN_RAW, $raw);
    }

    /**
     * The change of the cost as a share of the old cost, e.g. 0.18; null for new rows.
     */
    public function costChangeShare(): ?float
    {
        $old = $this->getOldCost();
        $new = $this->getCost();

        return $old === null || $old === 0 || $new === null ? null : ($new - $old) / $old;
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
            self::COLUMN_PRICE_IMPORT_ID => 'integer',
            self::COLUMN_ROW_NUMBER => 'integer',
            self::COLUMN_PROVIDER_ID => 'integer',
            self::COLUMN_VARIANT_ID => 'integer',
            self::COLUMN_COST => 'integer',
            self::COLUMN_IS_AVAILABLE => 'boolean',
            self::COLUMN_IS_ENABLED => 'boolean',
            self::COLUMN_LEAD_TIME_HOURS => 'integer',
            self::COLUMN_OLD_COST => 'integer',
            self::COLUMN_OLD_IS_AVAILABLE => 'boolean',
            self::COLUMN_OLD_IS_ENABLED => 'boolean',
            self::COLUMN_SELLING_PRICE => 'integer',
            self::COLUMN_IS_NEW => 'boolean',
            self::COLUMN_CHANGES => 'boolean',
            self::COLUMN_RAW => 'array',
        ];
    }
}
