<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\PersianNormalizer;
use Carbon\CarbonImmutable;

/**
 * Input for creating or updating a variant. Prices in rial, weight in grams, sizes in millimetres. A null SKU is
 * generated.
 */
final readonly class ProductVariantData
{
    public function __construct(
        public string $titleFa,
        public ?string $sku,
        public ?string $barcode,
        public ?int $officialPrice,
        public int $price,
        public ?int $salePrice,
        public StockStatus $stockStatus,
        public ?CarbonImmutable $expiryDate,
        public bool $isDamagedBox,
        public ?int $weightG,
        public ?int $lengthMm,
        public ?int $widthMm,
        public ?int $heightMm,
        public bool $dimensionsVerified,
        public bool $shipSeparately,
        public bool $isFragile,
        public bool $isLiquid,
        public bool $requiresColdChain,
        public ?int $maxPerOrder,
        public bool $isActive,
    ) {}

    /**
     * Keys are the product_variants columns (ProductVariant::COLUMN_*).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $stock = $data[ProductVariant::COLUMN_STOCK_STATUS] ?? StockStatus::Available;
        $expiry = $data[ProductVariant::COLUMN_EXPIRY_DATE] ?? null;

        return new self(
            titleFa: PersianNormalizer::letters(trim((string) ($data[ProductVariant::COLUMN_TITLE_FA] ?? ''))),
            sku: self::text($data[ProductVariant::COLUMN_SKU] ?? null),
            barcode: self::text($data[ProductVariant::COLUMN_BARCODE] ?? null),
            officialPrice: self::number($data[ProductVariant::COLUMN_OFFICIAL_PRICE] ?? null),
            price: (int) self::number($data[ProductVariant::COLUMN_PRICE] ?? 0),
            salePrice: self::number($data[ProductVariant::COLUMN_SALE_PRICE] ?? null),
            stockStatus: $stock instanceof StockStatus ? $stock : StockStatus::from((string) $stock),
            expiryDate: blank($expiry) ? null : CarbonImmutable::parse($expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : (string) $expiry)->startOfDay(),
            isDamagedBox: (bool) ($data[ProductVariant::COLUMN_IS_DAMAGED_BOX] ?? false),
            weightG: self::number($data[ProductVariant::COLUMN_WEIGHT_G] ?? null),
            lengthMm: self::number($data[ProductVariant::COLUMN_LENGTH_MM] ?? null),
            widthMm: self::number($data[ProductVariant::COLUMN_WIDTH_MM] ?? null),
            heightMm: self::number($data[ProductVariant::COLUMN_HEIGHT_MM] ?? null),
            dimensionsVerified: (bool) ($data[ProductVariant::COLUMN_DIMENSIONS_VERIFIED] ?? false),
            shipSeparately: (bool) ($data[ProductVariant::COLUMN_SHIP_SEPARATELY] ?? false),
            isFragile: (bool) ($data[ProductVariant::COLUMN_IS_FRAGILE] ?? false),
            isLiquid: (bool) ($data[ProductVariant::COLUMN_IS_LIQUID] ?? false),
            requiresColdChain: (bool) ($data[ProductVariant::COLUMN_REQUIRES_COLD_CHAIN] ?? false),
            maxPerOrder: self::number($data[ProductVariant::COLUMN_MAX_PER_ORDER] ?? null),
            isActive: (bool) ($data[ProductVariant::COLUMN_IS_ACTIVE] ?? true),
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = trim(PersianNormalizer::digits((string) $value));

        return $value === '' ? null : $value;
    }

    private static function number(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) PersianNormalizer::digits((string) $value);
    }
}
