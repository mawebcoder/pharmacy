<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\ProductVariant;

/**
 * The product and SKU as an order item keeps them (roadmap D3.4 «Snapshot rule», owner 2026-11-14): everything the
 * order pages and later reports need, so editing or deleting the product never changes a past order.
 */
final readonly class OrderItemSnapshots
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, array{product: array<string, mixed>, sku: array<string, mixed>}> variant id => snapshot
     */
    public function for(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $snapshots = [];

        $variants = ProductVariant::query()
            ->withTrashed()
            ->with(['product' => fn ($query) => $query->withTrashed()->with(['brand', 'primaryCategory'])])
            ->whereKey(array_values(array_unique($variantIds)))
            ->get();

        foreach ($variants as $variant) {
            $product = $variant->product;
            $brand = $product?->getBrand();
            $category = $product?->getPrimaryCategory();

            $snapshots[$variant->getId()] = [
                'product' => [
                    'id' => $product?->getId(),
                    'name_fa' => $product?->getNameFa(),
                    'name_en' => $product?->getNameEn(),
                    'slug' => $product?->getSlug(),
                    'brand' => $brand === null ? null : ['id' => $brand->getId(), 'name' => $brand->getNameFa()],
                    'primary_category' => $category === null ? null : ['id' => $category->getId(), 'name' => $category->getNameFa()],
                    'product_type' => $product?->getProductType()->value,
                    'requires_prescription' => $product?->requiresPrescription(),
                    'irc_code' => $product?->getIrcCode(),
                ],
                'sku' => [
                    'id' => $variant->getId(),
                    'sku' => $variant->getSku(),
                    'barcode' => $variant->getBarcode(),
                    'title' => $variant->getTitleFa(),
                    'is_near_expiry' => $variant->isNearExpiry(),
                    'weight_g' => $variant->getWeightG(),
                    'length_mm' => $variant->getLengthMm(),
                    'width_mm' => $variant->getWidthMm(),
                    'height_mm' => $variant->getHeightMm(),
                    'is_fragile' => $variant->isFragile(),
                    'is_liquid' => $variant->isLiquid(),
                    'requires_cold_chain' => $variant->requiresColdChain(),
                ],
            ];
        }

        return $snapshots;
    }
}
