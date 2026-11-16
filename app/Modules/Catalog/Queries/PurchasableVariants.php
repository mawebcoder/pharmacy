<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\PurchasableVariantData;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Queries\StoreSettings;

/**
 * SKUs as the cart sees them (roadmap D13.3, S19), read fresh on every cart change so prices and stock are always
 * current. A SKU that is gone is simply missing from the result.
 */
final readonly class PurchasableVariants
{
    public function __construct(private StoreSettings $settings) {}

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PurchasableVariantData> variant id => data
     */
    public function find(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $variants = ProductVariant::query()
            ->withTrashed()
            ->whereKey($variantIds)
            ->with(['product' => fn ($products) => $products->withTrashed()->with(['brand', 'media', 'variants'])])
            ->get();
        $storeLimit = $this->settings->maxQuantityPerOrder();
        // The supplier each live flash deal sells from (S33, owner 2026-11-17).
        $dealProviders = FlashDeal::query()
            ->whereKey($variants->map(fn (ProductVariant $variant): ?int => $variant->getFlashDealId())->filter()->all())
            ->pluck(FlashDeal::COLUMN_PROVIDER_ID, FlashDeal::COLUMN_ID);
        $found = [];

        foreach ($variants as $variant) {
            $product = $variant->getProduct();

            if (! $product instanceof Product) {
                continue;
            }

            $found[$variant->getId()] = new PurchasableVariantData(
                variantId: $variant->getId(),
                productId: $product->getId(),
                productName: $product->getNameFa(),
                productSlug: $product->getSlug(),
                brandName: $product->getBrand()?->getNameFa(),
                variantTitle: $variant->getTitleFa(),
                hasOtherVariants: $product->getVariants()->filter(fn (ProductVariant $other): bool => $other->isActive() && ! $other->trashed())->count() > 1,
                sku: $variant->getSku(),
                price: $variant->currentPrice(),
                compareAtPrice: $variant->compareAtPrice(),
                maxPerOrder: max(1, $variant->getMaxPerOrder() ?? $product->getMaxPerOrder() ?? $storeLimit),
                refusal: $this->refusal($product, $variant),
                image: $product->getMainImage(),
                isOnSale: $variant->getSalePrice() !== null || $variant->getFlashDealPrice() !== null,
                productType: $product->getProductType()->value,
                requiresPrescription: $product->requiresPrescription(),
                flashDealId: $variant->getFlashDealId(),
                flashDealProviderId: $variant->getFlashDealId() === null || $dealProviders->get($variant->getFlashDealId()) === null
                    ? null
                    : (int) $dealProviders->get($variant->getFlashDealId()),
            );
        }

        return $found;
    }

    public function one(int $variantId): ?PurchasableVariantData
    {
        return $this->find([$variantId])[$variantId] ?? null;
    }

    private function refusal(Product $product, ProductVariant $variant): ?string
    {
        return match (true) {
            $product->trashed() || $variant->trashed() || ! $product->isActive() || ! $variant->isActive() => PurchasableVariantData::REFUSED_UNAVAILABLE,
            ! $product->isSellableType() || $product->requiresPrescription() => PurchasableVariantData::REFUSED_PRESCRIPTION,
            $variant->requiresColdChain() => PurchasableVariantData::REFUSED_COLD_CHAIN,
            ! $variant->isInStock() => PurchasableVariantData::REFUSED_OUT_OF_STOCK,
            default => null,
        };
    }
}
