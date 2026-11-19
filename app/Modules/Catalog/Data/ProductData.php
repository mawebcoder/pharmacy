<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a product. A null slug is generated from the names.
 */
final readonly class ProductData
{
    /**
     * @param  list<int>  $categoryIds  extra categories besides the primary one
     * @param  list<int>  $attributeValueIds  chosen attribute values (any attribute)
     * @param  list<int>  $tagIds
     * @param  list<int>  $subcategoryIds  extra subcategories besides the main one (owner, 2026-11-15)
     */
    public function __construct(
        public string $nameFa,
        public ?string $nameEn,
        public ?string $slug,
        public int $brandId,
        public ?float $commissionPercent,
        public int $primaryCategoryId,
        public array $categoryIds,
        public ProductType $productType,
        public ?string $ircCode,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $ingredients,
        public ?string $usage,
        public ?string $warnings,
        public ?string $sideEffects,
        public ?string $interactions,
        public ProductStatus $status,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public array $attributeValueIds = [],
        public array $tagIds = [],
        public ?int $maxPerOrder = null,
        public ?int $primarySubcategoryId = null,
        public array $subcategoryIds = [],
        public ?int $minShelfLifeMonths = null,
        public ?float $harajPercent = null,
        public ?int $tipaxContentId = null,
        public bool $isLiquid = false,
        public bool $isFragile = false,
    ) {}

    /**
     * Keys are the products columns (Product::COLUMN_*) plus "category_ids", "subcategory_ids", "attribute_value_ids" and
     * "tag_ids".
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data[Product::COLUMN_PRODUCT_TYPE] ?? ProductType::Cosmetic;
        $status = $data[Product::COLUMN_STATUS] ?? ProductStatus::Draft;
        $categoryIds = $data['category_ids'] ?? [];

        return new self(
            nameFa: PersianNormalizer::letters(trim((string) ($data[Product::COLUMN_NAME_FA] ?? ''))),
            nameEn: self::text($data[Product::COLUMN_NAME_EN] ?? null),
            slug: self::text($data[Product::COLUMN_SLUG] ?? null),
            brandId: (int) ($data[Product::COLUMN_BRAND_ID] ?? 0),
            commissionPercent: self::percent($data[Product::COLUMN_COMMISSION_PERCENT] ?? null),
            primaryCategoryId: (int) ($data[Product::COLUMN_PRIMARY_CATEGORY_ID] ?? 0),
            categoryIds: self::ids($categoryIds),
            productType: $type instanceof ProductType ? $type : ProductType::from((string) $type),
            ircCode: self::text($data[Product::COLUMN_IRC_CODE] ?? null),
            shortDescription: self::text($data[Product::COLUMN_SHORT_DESCRIPTION] ?? null),
            description: self::text($data[Product::COLUMN_DESCRIPTION] ?? null),
            ingredients: self::text($data[Product::COLUMN_INGREDIENTS] ?? null),
            usage: self::text($data[Product::COLUMN_USAGE] ?? null),
            warnings: self::text($data[Product::COLUMN_WARNINGS] ?? null),
            sideEffects: self::text($data[Product::COLUMN_SIDE_EFFECTS] ?? null),
            interactions: self::text($data[Product::COLUMN_INTERACTIONS] ?? null),
            status: $status instanceof ProductStatus ? $status : ProductStatus::from((string) $status),
            seoTitle: self::text($data[Product::COLUMN_SEO_TITLE] ?? null),
            seoDescription: self::text($data[Product::COLUMN_SEO_DESCRIPTION] ?? null),
            attributeValueIds: self::ids($data['attribute_value_ids'] ?? []),
            tagIds: self::ids($data['tag_ids'] ?? []),
            maxPerOrder: blank($data[Product::COLUMN_MAX_PER_ORDER] ?? null) ? null : (int) PersianNormalizer::digits((string) $data[Product::COLUMN_MAX_PER_ORDER]),
            primarySubcategoryId: blank($data[Product::COLUMN_PRIMARY_SUBCATEGORY_ID] ?? null) ? null : (int) $data[Product::COLUMN_PRIMARY_SUBCATEGORY_ID],
            subcategoryIds: self::ids($data['subcategory_ids'] ?? []),
            harajPercent: blank($data[Product::COLUMN_HARAJ_PERCENT] ?? null) ? null : (float) $data[Product::COLUMN_HARAJ_PERCENT],
            minShelfLifeMonths: blank($data[Product::COLUMN_MIN_SHELF_LIFE_MONTHS] ?? null) ? null : (int) PersianNormalizer::digits((string) $data[Product::COLUMN_MIN_SHELF_LIFE_MONTHS]),
            tipaxContentId: blank($data[Product::COLUMN_TIPAX_CONTENT_ID] ?? null) ? null : (int) $data[Product::COLUMN_TIPAX_CONTENT_ID],
            isLiquid: (bool) ($data[Product::COLUMN_IS_LIQUID] ?? false),
            isFragile: (bool) ($data[Product::COLUMN_IS_FRAGILE] ?? false),
        );
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $ids): array
    {
        return is_array($ids) ? array_values(array_unique(array_map(intval(...), array_filter($ids, is_numeric(...))))) : [];
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function percent(mixed $value): ?float
    {
        $value = trim(PersianNormalizer::digits((string) $value));

        return $value === '' ? null : (float) $value;
    }
}
