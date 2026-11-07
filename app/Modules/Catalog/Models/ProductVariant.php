<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The sellable unit of a product (roadmap D3.1), e.g. "۷۵ میلی‌لیتر". Money in rial, weight in grams, sizes in mm.
 */
final class ProductVariant extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_SKU = 'sku';

    public const string COLUMN_BARCODE = 'barcode';

    public const string COLUMN_TITLE_FA = 'title_fa';

    public const string COLUMN_OFFICIAL_PRICE = 'official_price';

    public const string COLUMN_PRICE = 'price';

    public const string COLUMN_SALE_PRICE = 'sale_price';

    public const string COLUMN_STOCK_STATUS = 'stock_status';

    public const string COLUMN_EXPIRY_DATE = 'expiry_date';

    public const string COLUMN_IS_NEAR_EXPIRY = 'is_near_expiry';

    public const string COLUMN_IS_DAMAGED_BOX = 'is_damaged_box';

    public const string COLUMN_WEIGHT_G = 'weight_g';

    public const string COLUMN_LENGTH_MM = 'length_mm';

    public const string COLUMN_WIDTH_MM = 'width_mm';

    public const string COLUMN_HEIGHT_MM = 'height_mm';

    public const string COLUMN_DIMENSIONS_VERIFIED = 'dimensions_verified';

    public const string COLUMN_SHIP_SEPARATELY = 'ship_separately';

    public const string COLUMN_IS_FRAGILE = 'is_fragile';

    public const string COLUMN_IS_LIQUID = 'is_liquid';

    public const string COLUMN_REQUIRES_COLD_CHAIN = 'requires_cold_chain';

    public const string COLUMN_MAX_PER_ORDER = 'max_per_order';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public const string COLUMN_DELETED_AT = 'deleted_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProductId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRODUCT_ID);
    }

    public function setProductId(int $value): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_ID, $value);
    }

    public function getSku(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SKU);
    }

    public function setSku(string $value): static
    {
        return $this->setAttribute(self::COLUMN_SKU, $value);
    }

    public function getBarcode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_BARCODE);

        return $value === null ? null : (string) $value;
    }

    public function setBarcode(?string $value): static
    {
        return $this->setAttribute(self::COLUMN_BARCODE, $value);
    }

    public function getTitleFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE_FA);
    }

    public function setTitleFa(string $value): static
    {
        return $this->setAttribute(self::COLUMN_TITLE_FA, $value);
    }

    /**
     * Consumer price printed on the box, in rial (legal cap for medicines).
     */
    public function getOfficialPrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_OFFICIAL_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setOfficialPrice(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_OFFICIAL_PRICE, $value);
    }

    /**
     * Our selling price in rial.
     */
    public function getPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRICE);
    }

    public function setPrice(int $value): static
    {
        return $this->setAttribute(self::COLUMN_PRICE, $value);
    }

    /**
     * Discounted price in rial while a discount is active.
     */
    public function getSalePrice(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_SALE_PRICE);

        return $value === null ? null : (int) $value;
    }

    public function setSalePrice(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_SALE_PRICE, $value);
    }

    public function isNearExpiry(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_NEAR_EXPIRY);
    }

    public function getIsNearExpiry(): bool
    {
        return $this->isNearExpiry();
    }

    public function setIsNearExpiry(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_NEAR_EXPIRY, $value);
    }

    public function isDamagedBox(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_DAMAGED_BOX);
    }

    public function getIsDamagedBox(): bool
    {
        return $this->isDamagedBox();
    }

    public function setIsDamagedBox(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_DAMAGED_BOX, $value);
    }

    /**
     * Packaged weight in grams.
     */
    public function getWeightG(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_WEIGHT_G);

        return $value === null ? null : (int) $value;
    }

    public function setWeightG(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_WEIGHT_G, $value);
    }

    /**
     * Packaged length in millimetres.
     */
    public function getLengthMm(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_LENGTH_MM);

        return $value === null ? null : (int) $value;
    }

    public function setLengthMm(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_LENGTH_MM, $value);
    }

    public function getWidthMm(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_WIDTH_MM);

        return $value === null ? null : (int) $value;
    }

    public function setWidthMm(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_WIDTH_MM, $value);
    }

    public function getHeightMm(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_HEIGHT_MM);

        return $value === null ? null : (int) $value;
    }

    public function setHeightMm(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_HEIGHT_MM, $value);
    }

    public function areDimensionsVerified(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_DIMENSIONS_VERIFIED);
    }

    public function getDimensionsVerified(): bool
    {
        return $this->areDimensionsVerified();
    }

    public function setDimensionsVerified(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_DIMENSIONS_VERIFIED, $value);
    }

    public function shipsSeparately(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_SHIP_SEPARATELY);
    }

    public function getShipSeparately(): bool
    {
        return $this->shipsSeparately();
    }

    public function setShipSeparately(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_SHIP_SEPARATELY, $value);
    }

    public function isFragile(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_FRAGILE);
    }

    public function getIsFragile(): bool
    {
        return $this->isFragile();
    }

    public function setIsFragile(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_FRAGILE, $value);
    }

    public function isLiquid(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_LIQUID);
    }

    public function getIsLiquid(): bool
    {
        return $this->isLiquid();
    }

    public function setIsLiquid(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_LIQUID, $value);
    }

    public function requiresColdChain(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_REQUIRES_COLD_CHAIN);
    }

    public function getRequiresColdChain(): bool
    {
        return $this->requiresColdChain();
    }

    public function setRequiresColdChain(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_REQUIRES_COLD_CHAIN, $value);
    }

    public function getMaxPerOrder(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_MAX_PER_ORDER);

        return $value === null ? null : (int) $value;
    }

    public function setMaxPerOrder(?int $value): static
    {
        return $this->setAttribute(self::COLUMN_MAX_PER_ORDER, $value);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function getIsActive(): bool
    {
        return $this->isActive();
    }

    public function setIsActive(bool $value): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $value);
    }

    public function getStockStatus(): StockStatus
    {
        return $this->getAttribute(self::COLUMN_STOCK_STATUS);
    }

    public function setStockStatus(StockStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STOCK_STATUS, $status);
    }

    public function getExpiryDate(): ?CarbonImmutable
    {
        return $this->getAttribute(self::COLUMN_EXPIRY_DATE);
    }

    public function setExpiryDate(?CarbonImmutable $date): static
    {
        return $this->setAttribute(self::COLUMN_EXPIRY_DATE, $date);
    }

    /**
     * The price the customer pays now, in rial: the sale price when set, else the price.
     */
    public function currentPrice(): int
    {
        return $this->getSalePrice() ?? $this->getPrice();
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    public function getDeletedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_DELETED_AT);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getProduct(): ?Product
    {
        return $this->getRelationValue('product');
    }

    /**
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logAll()
            ->logExcept([self::COLUMN_ID, self::COLUMN_CREATED_AT, self::COLUMN_UPDATED_AT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'تنوع محصول: '.$this->getSku().' ('.$this->getTitleFa().')';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PRODUCT_ID => 'integer',
            self::COLUMN_OFFICIAL_PRICE => 'integer',
            self::COLUMN_PRICE => 'integer',
            self::COLUMN_SALE_PRICE => 'integer',
            self::COLUMN_STOCK_STATUS => StockStatus::class,
            self::COLUMN_EXPIRY_DATE => 'immutable_date',
            self::COLUMN_IS_NEAR_EXPIRY => 'boolean',
            self::COLUMN_IS_DAMAGED_BOX => 'boolean',
            self::COLUMN_WEIGHT_G => 'integer',
            self::COLUMN_LENGTH_MM => 'integer',
            self::COLUMN_WIDTH_MM => 'integer',
            self::COLUMN_HEIGHT_MM => 'integer',
            self::COLUMN_DIMENSIONS_VERIFIED => 'boolean',
            self::COLUMN_SHIP_SEPARATELY => 'boolean',
            self::COLUMN_IS_FRAGILE => 'boolean',
            self::COLUMN_IS_LIQUID => 'boolean',
            self::COLUMN_REQUIRES_COLD_CHAIN => 'boolean',
            self::COLUMN_MAX_PER_ORDER => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
