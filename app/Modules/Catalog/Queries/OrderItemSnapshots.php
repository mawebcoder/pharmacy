<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shipping\Queries\VariantBoxCapacities;

/**
 * The product and SKU as an order item keeps them (roadmap D3.4 «Snapshot rule», owner 2026-11-14): everything the
 * order pages and later reports need, so editing or deleting the product never changes a past order.
 */
final readonly class OrderItemSnapshots
{
    public function __construct(
        private VariantBoxCapacities $boxCapacities,
    ) {}

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
        $boxCapacities = $this->boxCapacities->for(array_values(array_unique($variantIds)));

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
                    // What the product is for Tipax (owner, 2026-11-19: per product, not per SKU).
                    'tipax_content_id' => $product?->getTipaxContentId(),
                    'is_liquid' => (bool) $product?->isLiquid(),
                    'is_fragile' => (bool) $product?->isFragile(),
                ],
                'sku' => [
                    'id' => $variant->getId(),
                    'sku' => $variant->getSku(),
                    'barcode' => $variant->getBarcode(),
                    'title' => $variant->getTitleFa(),
                    'weight_g' => $variant->getWeightG(),
                    // How many fit each box (box id => pieces), for packing the paid order (S31, owner 2026-11-18).
                    'box_capacities' => $boxCapacities[$variant->getId()] ?? [],
                    'ship_separately' => $variant->shipsSeparately(),
                    'length_mm' => $variant->getLengthMm(),
                    'width_mm' => $variant->getWidthMm(),
                    'height_mm' => $variant->getHeightMm(),
                    'requires_cold_chain' => $variant->requiresColdChain(),
                ],
            ];
        }

        return $snapshots;
    }
}
