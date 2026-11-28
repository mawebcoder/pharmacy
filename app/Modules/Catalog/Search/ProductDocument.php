<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Shared\Support\SearchText;

/**
 * The Meilisearch document of a product (roadmap D21.5.4, decided 2026-11-07 in S12). Every product that is not
 * deleted has one; is_visible says whether shoppers may see it, and every storefront query filters on it. Text fields are normalised
 * with SearchText, the same way queries are. price, discount_pct and in_stock come from the default SKU
 * (Product::getDefaultVariant()), so filters and sorting match the card; variants lists every active SKU.
 * category_ids holds the categories of its subcategories too, so a category lists its subcategories' products;
 * subcategory_ids holds its main and extra subcategories (owner, 2026-11-15: subcategories in their own table).
 */
final class ProductDocument
{
    /**
     * Relations a document needs; load them for many products at once (Product::makeSearchableUsing).
     */
    public const array RELATIONS = [
        'brand', 'primaryCategory', 'categories', 'primarySubcategory', 'subcategories', 'tags', 'attributeValues', 'variants', 'collections',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function for(Product $product): array
    {
        $variants = $product->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive() && ! $variant->trashed());
        $subcategories = collect([$product->getPrimarySubcategory(), ...$product->getSubcategories()->all()])->filter();
        $categoryIds = [];
        $categoryNames = [];
        $subcategoryNames = [];

        /** @var Category $category */
        foreach (collect([$product->getPrimaryCategory(), ...$product->getCategories()->all()])->filter() as $category) {
            $categoryIds[] = $category->getId();
            $categoryNames[] = $category->getNameFa();
        }

        /** @var Subcategory $subcategory */
        foreach ($subcategories as $subcategory) {
            $categoryIds[] = $subcategory->getCategoryId();
            $subcategoryNames[] = $subcategory->getNameFa();
        }

        $default = $product->getDefaultVariant();

        return [
            'id' => $product->getId(),
            // Every product is indexed; the storefront asks for is_visible = true (owner, 2026-11-27).
            'is_visible' => $product->isShownOnStorefront(),
            'status' => $product->getStatus()->value,
            'name_fa' => SearchText::forIndex($product->getNameFa()),
            'name_en' => SearchText::forIndex((string) $product->getNameEn()),
            'brand_id' => $product->getBrandId(),
            'brand_fa' => SearchText::forIndex((string) $product->getBrand()?->getNameFa()),
            'brand_en' => SearchText::forIndex((string) $product->getBrand()?->getNameEn()),
            'category_ids' => array_values(array_unique($categoryIds)),
            'subcategory_ids' => array_values(array_unique($subcategories->map(fn (Subcategory $subcategory): int => $subcategory->getId())->all())),
            'category_names' => array_values(array_unique(array_map(SearchText::forIndex(...), $categoryNames))),
            'subcategory_names' => array_values(array_unique(array_map(SearchText::forIndex(...), $subcategoryNames))),
            'collection_ids' => $product->getCollections()->map(fn (ProductCollection $collection): int => $collection->getId())->values()->all(),
            'tag_ids' => $product->getTags()->map(fn (Tag $tag): int => $tag->getId())->values()->all(),
            'tags' => $product->getTags()->map(fn (Tag $tag): string => SearchText::forIndex($tag->getNameFa().' '.$tag->getNameEn()))->values()->all(),
            'attribute_value_ids' => $product->getAttributeValues()->map(fn (AttributeValue $value): int => $value->getId())->values()->all(),
            'attribute_values' => $product->getAttributeValues()->map(fn (AttributeValue $value): string => SearchText::forIndex($value->getValueFa()))->values()->all(),
            'ingredients' => SearchText::normalize(mb_substr((string) $product->getIngredients(), 0, 300)),
            // The sound key of the name, brand and categories, searched only when nothing else matched (S39b).
            'phonetic' => SearchText::phoneticForIndex(implode(' ', [$product->getNameFa(), $product->getNameEn(), $product->getBrand()?->getNameFa(), $product->getBrand()?->getNameEn(), ...$categoryNames, ...$subcategoryNames])),
            // SKU, barcode and the IRC code (S39), matched exactly: typos are off for codes.
            'codes' => $variants->flatMap(fn (ProductVariant $variant): array => array_filter([$variant->getSku(), $variant->getBarcode()]))
                ->concat(array_filter([$product->getIrcCode()]))
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
            // A SKU with a live flash deal (S28): the «پیشنهاد لحظه‌ای» listing filter.
            'has_flash_deal' => $variants->contains(fn (ProductVariant $variant): bool => $variant->getFlashDealId() !== null),
            'product_type' => $product->getProductType()->value,
            // Units sold in the last 30 days (S39): ranks popular products higher and sorts «پرفروش‌ترین».
            'sales_30d' => $product->getSales30d(),
            'created_at' => $product->getCreatedAt()?->getTimestamp() ?? 0,
            // For sitemap.xml, read from the index (owner, 2026-11-27).
            'slug' => $product->getSlug(),
            'updated_at' => $product->getUpdatedAt()?->getTimestamp() ?? 0,
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
            // In score order (owner, 2026-11-28): the product's own name and codes, then its brand, subcategory, category
            // and tags, so searching a brand or a category lists its products, below any whose name matches. The phonetic
            // field last, so it never outranks a real match.
            'searchableAttributes' => ['name_fa', 'name_en', 'codes', 'brand_fa', 'brand_en', 'subcategory_names', 'category_names', 'tags', 'attribute_values', 'ingredients', 'phonetic'],
            'filterableAttributes' => ['is_visible', 'status', 'category_ids', 'subcategory_ids', 'brand_id', 'tag_ids', 'collection_ids', 'attribute_value_ids', 'price', 'discount_pct', 'in_stock', 'is_near_expiry', 'has_flash_deal', 'product_type'],
            'sortableAttributes' => ['price', 'discount_pct', 'sales_30d', 'created_at'],
            // attributeRank (the field order above) right after words: a name match, even with a typo, comes before an
            // exact brand or category match.
            'rankingRules' => ['words', 'attributeRank', 'typo', 'proximity', 'wordPosition', 'sort', 'exactness', 'in_stock_rank:desc', 'sales_30d:desc'],
            'typoTolerance' => [
                // One typo from 3 letters (owner, 2026-11-28): many Persian product words are 3 letters («کرم»، «قرص»), so
                // «کمر» still finds «کرم»; exact words rank first (the «typo» ranking rule).
                'minWordSizeForTypos' => ['oneTypo' => 3, 'twoTypos' => 8],
                'disableOnAttributes' => ['codes'],
            ],
            'pagination' => ['maxTotalHits' => 2000],
            // Persian fields are Persian, English names English: a short query is never read as Arabic (S39b).
            'localizedAttributes' => [
                ['attributePatterns' => ['name_fa', 'brand_fa', 'tags', 'category_names', 'subcategory_names', 'attribute_values', 'ingredients'], 'locales' => ['pes']],
                ['attributePatterns' => ['name_en', 'brand_en'], 'locales' => ['eng']],
                // Sound keys of both the Persian and the English names.
                ['attributePatterns' => ['phonetic'], 'locales' => ['pes', 'eng']],
            ],
            'faceting' => ['maxValuesPerFacet' => 200],
        ];
    }
}
