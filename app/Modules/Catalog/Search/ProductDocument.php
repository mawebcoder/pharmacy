<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\SearchText;

/**
 * The Meilisearch document of a product (roadmap D21.5.4, decided 2026-11-07 in S12). Text fields are normalised
 * with SearchText, the same way queries are. price, discount_pct and in_stock come from the default SKU
 * (Product::getDefaultVariant()), so filters and sorting match the card; variants lists every active SKU.
 * category_ids holds every category above the product's categories too, so a parent category lists its
 * subcategories' products.
 */
final class ProductDocument
{
    /**
     * Relations a document needs; load them for many products at once (Product::makeSearchableUsing).
     */
    public const array RELATIONS = [
        'brand', 'primaryCategory.parent.parent', 'categories.parent.parent', 'tags', 'attributeValues', 'variants',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function for(Product $product): array
    {
        $variants = $product->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive() && ! $variant->trashed());
        $categories = collect([$product->getPrimaryCategory(), ...$product->getCategories()->all()])->filter();
        $categoryIds = [];
        $categoryNames = [];

        /** @var Category $category */
        foreach ($categories as $category) {
            foreach ([...$category->ancestors(), $category] as $node) {
                $categoryIds[] = $node->getId();
                $categoryNames[] = $node->getNameFa();
            }
        }

        $default = $product->getDefaultVariant();

        return [
            'id' => $product->getId(),
            'name_fa' => SearchText::forIndex($product->getNameFa()),
            'name_en' => SearchText::normalize((string) $product->getNameEn()),
            'brand_id' => $product->getBrandId(),
            'brand_fa' => SearchText::forIndex((string) $product->getBrand()?->getNameFa()),
            'brand_en' => SearchText::normalize((string) $product->getBrand()?->getNameEn()),
            'category_ids' => array_values(array_unique($categoryIds)),
            'category_names' => array_values(array_unique(array_map(SearchText::forIndex(...), $categoryNames))),
            'tag_ids' => $product->getTags()->map(fn (Tag $tag): int => $tag->getId())->values()->all(),
            'tags' => $product->getTags()->map(fn (Tag $tag): string => SearchText::forIndex($tag->getNameFa().' '.$tag->getNameEn()))->values()->all(),
            'attribute_value_ids' => $product->getAttributeValues()->map(fn (AttributeValue $value): int => $value->getId())->values()->all(),
            'attribute_values' => $product->getAttributeValues()->map(fn (AttributeValue $value): string => SearchText::forIndex($value->getValueFa()))->values()->all(),
            'ingredients' => SearchText::normalize(mb_substr((string) $product->getIngredients(), 0, 300)),
            'codes' => $variants->flatMap(fn (ProductVariant $variant): array => array_filter([$variant->getSku(), $variant->getBarcode()]))
                ->map(fn (string $code): string => mb_strtolower($code))
                ->values()
                ->all(),
            'default_variant' => $default instanceof ProductVariant ? [
                ...self::variant($default),
                'final_price' => $default->currentPrice(),
                'discount_pct' => $default->discountPercent(),
                'max_per_order' => $default->getMaxPerOrder(),
            ] : null,
            'variants' => $variants->map(self::variant(...))->values()->all(),
            'price' => $default?->currentPrice() ?? 0,
            'discount_pct' => $default?->discountPercent() ?? 0,
            'in_stock' => $default?->isInStock() ?? false,
            'in_stock_rank' => $default?->isInStock() ? 1 : 0,
            'is_near_expiry' => $variants->contains(fn (ProductVariant $variant): bool => $variant->isNearExpiry()),
            'is_damaged_box' => $variants->contains(fn (ProductVariant $variant): bool => $variant->isDamagedBox()),
            'product_type' => $product->getProductType()->value,
            // Orders arrive in S20; until then best-selling sorts like newest.
            'sales_30d' => 0,
            'created_at' => $product->getCreatedAt()?->getTimestamp() ?? 0,
        ];
    }

    /**
     * One SKU as stored in the document: prices in rial (compare_at_price is the struck-through price).
     *
     * @return array{id: int, sku: string, title_fa: string, price: int, sale_price: int|null, compare_at_price: int|null, in_stock: bool}
     */
    private static function variant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->getId(),
            'sku' => $variant->getSku(),
            'title_fa' => $variant->getTitleFa(),
            'price' => $variant->getPrice(),
            'sale_price' => $variant->getSalePrice(),
            'compare_at_price' => $variant->compareAtPrice(),
            'in_stock' => $variant->isInStock(),
        ];
    }

    /**
     * Meilisearch index settings (applied with `php artisan scout:sync-index-settings`).
     *
     * @return array<string, mixed>
     */
    public static function indexSettings(): array
    {
        return [
            'searchableAttributes' => ['name_fa', 'name_en', 'brand_fa', 'brand_en', 'tags', 'category_names', 'attribute_values', 'ingredients', 'codes'],
            'filterableAttributes' => ['category_ids', 'brand_id', 'tag_ids', 'attribute_value_ids', 'price', 'discount_pct', 'in_stock', 'is_near_expiry', 'is_damaged_box', 'product_type'],
            'sortableAttributes' => ['price', 'discount_pct', 'sales_30d', 'created_at'],
            'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'in_stock_rank:desc', 'sales_30d:desc'],
            'typoTolerance' => [
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
                'disableOnAttributes' => ['codes'],
            ],
            'pagination' => ['maxTotalHits' => 2000],
            'faceting' => ['maxValuesPerFacet' => 200],
        ];
    }
}
