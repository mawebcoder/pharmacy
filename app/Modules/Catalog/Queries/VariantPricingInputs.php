<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\VariantPricingInput;
use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Catalog side of a SKU's price for Pricing (roadmap D3.2): printed price, haraj, current price and the
 * commission (the product's own, else its brand's). Includes trashed SKUs' products so a price can still be read.
 */
final readonly class VariantPricingInputs
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, VariantPricingInput> keyed by variant id
     */
    public function for(array $variantIds): array
    {
        $inputs = [];

        $variants = ProductVariant::query()
            ->withTrashed()
            ->whereKey($variantIds)
            ->with(['product' => fn ($products) => $products->withTrashed()->with(['brand' => fn ($brands) => $brands->withTrashed()])])
            ->get();

        foreach ($variants as $variant) {
            $product = $variant->getProduct();
            $productCommission = $product?->getCommissionPercent();
            $brandCommission = $product?->getBrand()?->getCommissionPercent();

            $inputs[$variant->getId()] = new VariantPricingInput(
                variantId: $variant->getId(),
                productId: $variant->getProductId(),
                officialPrice: $variant->getOfficialPrice(),
                salePrice: $variant->getSalePrice(),
                price: $variant->getPrice(),
                currentPriceId: $variant->getCurrentPriceId(),
                commissionPercent: $productCommission ?? $brandCommission,
                commissionSource: match (true) {
                    $productCommission !== null => 'product',
                    $brandCommission !== null => 'brand',
                    default => null,
                },
                stockStatus: $variant->getStockStatus(),
            );
        }

        return $inputs;
    }

    /**
     * One SKU for an admin page, or null when it does not exist (trashed SKUs included).
     */
    public function summary(int $variantId): ?VariantSummary
    {
        return $this->summaries([$variantId])[$variantId] ?? null;
    }

    /**
     * SKUs for admin tables (e.g. a supplier's SKUs), keyed by variant id; trashed SKUs included.
     *
     * @param  list<int>  $variantIds
     * @return array<int, VariantSummary>
     */
    public function summaries(array $variantIds): array
    {
        $inputs = $this->for($variantIds);
        $summaries = [];

        $variants = ProductVariant::query()->withTrashed()->with(['product' => fn ($products) => $products->withTrashed()])->whereKey($variantIds)->get();

        foreach ($variants as $variant) {
            $product = $variant->getProduct();
            $input = $inputs[$variant->getId()] ?? null;

            if (! $product instanceof Product || ! $input instanceof VariantPricingInput) {
                continue;
            }

            $summaries[$variant->getId()] = new VariantSummary(
                variantId: $variant->getId(),
                productId: $product->getId(),
                productName: $product->getNameFa(),
                productRouteKey: (string) $product->getRouteKey(),
                title: $variant->getTitleFa(),
                sku: $variant->getSku(),
                barcode: $variant->getBarcode(),
                officialPrice: $variant->getOfficialPrice(),
                price: $variant->getPrice(),
                salePrice: $variant->getSalePrice(),
                stockStatus: $variant->getStockStatus(),
                commissionPercent: $input->commissionPercent,
                commissionSource: $input->commissionSource,
            );
        }

        return $summaries;
    }

    /**
     * The commission a product's SKUs use: its own, else its brand's; null when neither has one.
     */
    public function commissionOf(Product $product): ?float
    {
        return $product->getCommissionPercent() ?? $product->loadMissing('brand')->getBrand()?->getCommissionPercent();
    }

    /**
     * @return list<int>
     */
    public function variantIdsOfProduct(int $productId): array
    {
        return array_values(ProductVariant::query()->where(ProductVariant::COLUMN_PRODUCT_ID, $productId)->pluck(ProductVariant::COLUMN_ID)->map(intval(...))->all());
    }

    /**
     * SKUs whose price follows the brand's commission: products of the brand without a commission of their own.
     *
     * @return list<int>
     */
    public function variantIdsFollowingBrand(Brand $brand): array
    {
        return array_values(ProductVariant::query()
            ->whereHas('product', fn (Builder $products) => $products->withTrashed()
                ->where(Product::COLUMN_BRAND_ID, $brand->getId())
                ->whereNull(Product::COLUMN_COMMISSION_PERCENT))
            ->pluck(ProductVariant::COLUMN_ID)
            ->map(intval(...))
            ->all());
    }
}
