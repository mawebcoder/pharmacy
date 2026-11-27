<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Seo\Data\SeoMeta;
use App\Modules\Seo\Support\SeoText;
use App\Modules\Seo\Support\StructuredData;

/**
 * What the product page tells search engines (roadmap S38): its description, share photo, and Product and
 * BreadcrumbList JSON-LD. Prices are in rial (IRR), as stored; prescription drugs have no offer, as they are not sold
 * online yet.
 */
final class ProductSeo
{
    public static function meta(Product $product, ?ProductVariant $selected): SeoMeta
    {
        return new SeoMeta(
            description: self::description($product),
            image: StructuredData::absolute($product->getMainImage()?->url(1200, 'jpg')),
            type: 'product',
            structuredData: [self::product($product, $selected), StructuredData::breadcrumbs(self::trail($product))],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product, ?ProductVariant $selected): array
    {
        $variants = array_values($product->getVariants()->filter(fn (ProductVariant $variant): bool => $variant->isActive())->all());
        $variant = $selected ?? ($variants[0] ?? null);
        $images = array_values(array_filter(array_map(
            fn (StoredImage $image): ?string => StructuredData::absolute($image->url(1200, 'jpg')),
            $product->images(Product::IMAGE_GALLERY),
        )));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getNameFa(),
            'alternateName' => $product->getNameEn(),
            'description' => self::description($product),
            'image' => $images === [] ? null : $images,
            'sku' => $variant?->getSku(),
            'gtin13' => preg_match('/^\d{13}$/', (string) $variant?->getBarcode()) === 1 ? $variant?->getBarcode() : null,
            'brand' => $product->getBrand() === null ? null : ['@type' => 'Brand', 'name' => $product->getBrand()->getNameFa()],
            'category' => $product->getPrimaryCategory()?->getNameFa(),
            'offers' => $product->isSellableType() && $variant !== null ? self::offers($product, $variants, $variant) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function description(Product $product): ?string
    {
        return SeoText::description($product->getSeoDescription(), $product->getShortDescription(), $product->getDescription());
    }

    /**
     * Home › category › main subcategory › product.
     *
     * @return list<array{name: string, url: string}>
     */
    private static function trail(Product $product): array
    {
        $category = $product->getPrimaryCategory();
        $subcategory = $product->getPrimarySubcategory()?->setRelation('category', $category);
        $trail = [['name' => (string) config('app.display_name'), 'url' => route('home')]];

        foreach (array_filter([$category, $subcategory]) as $node) {
            $trail[] = ['name' => $node->getNameFa(), 'url' => $node->url()];
        }

        $trail[] = ['name' => $product->getNameFa(), 'url' => route('products.show', $product->getSlug())];

        return $trail;
    }

    /**
     * One offer for a single SKU; with several, an AggregateOffer over their prices, keeping the shown SKU's.
     *
     * @param  list<ProductVariant>  $variants
     * @return array<string, mixed>
     */
    private static function offers(Product $product, array $variants, ProductVariant $variant): array
    {
        $offer = [
            '@type' => 'Offer',
            'url' => route('products.show', $product->getSlug()),
            'priceCurrency' => 'IRR',
            'price' => $variant->currentPrice(),
            'availability' => match ($variant->getStockStatus()) {
                StockStatus::Available => 'https://schema.org/InStock',
                StockStatus::OnRequest => 'https://schema.org/BackOrder',
                StockStatus::OutOfStock => 'https://schema.org/OutOfStock',
            },
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', 'name' => (string) config('app.display_name')],
        ];

        if (count($variants) < 2) {
            return $offer;
        }

        $prices = array_map(fn (ProductVariant $item): int => $item->currentPrice(), $variants);

        return [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'IRR',
            'lowPrice' => min($prices),
            'highPrice' => max($prices),
            'offerCount' => count($variants),
            'offers' => [$offer],
        ];
    }
}
