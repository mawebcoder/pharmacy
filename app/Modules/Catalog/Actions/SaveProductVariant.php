<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\NearExpiry;
use App\Modules\Catalog\Support\VariantPricing;
use App\Modules\Catalog\Support\VariantSku;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a variant of a product: checks the price rules (VariantPricing), generates a missing SKU,
 * sets the near-expiry flag from the expiry date, and keeps an active product sellable (it must keep an active
 * variant).
 */
final class SaveProductVariant
{
    /**
     * @throws ProductChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Product $product, ProductVariantData $data, ?ProductVariant $variant = null): ProductVariant
    {
        VariantPricing::assertValid($product, $data);

        if ($variant instanceof ProductVariant && $variant->isActive() && ! $data->isActive) {
            DeleteProductVariant::assertNotLastActive($product, $variant);
        }

        $saved = DB::transaction(function () use ($product, $data, $variant): ProductVariant {
            $variant ??= (new ProductVariant)->setProductId($product->getId());

            $variant
                ->setSku($data->sku ?? ($variant->exists ? $variant->getSku() : VariantSku::next($product->loadMissing('brand'))))
                ->setBarcode($data->barcode)
                ->setTitleFa($data->titleFa)
                ->setOfficialPrice($data->officialPrice)
                ->setPrice($data->price)
                ->setSalePrice($data->salePrice)
                ->setStockStatus($data->stockStatus)
                ->setExpiryDate($data->expiryDate)
                ->setIsNearExpiry(NearExpiry::isNear($data->expiryDate))
                ->setIsDamagedBox($data->isDamagedBox)
                ->setWeightG($data->weightG)
                ->setLengthMm($data->lengthMm)
                ->setWidthMm($data->widthMm)
                ->setHeightMm($data->heightMm)
                ->setDimensionsVerified($data->dimensionsVerified)
                ->setShipSeparately($data->shipSeparately)
                ->setIsFragile($data->isFragile)
                ->setIsLiquid($data->isLiquid)
                ->setRequiresColdChain($data->requiresColdChain)
                ->setMaxPerOrder($data->maxPerOrder)
                ->setIsActive($data->isActive)
                ->save();

            return $variant;
        });

        ProductSearchIndex::refresh($product);

        return $saved;
    }
}
