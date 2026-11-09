<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Enums\PricingInputChange;
use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Events\PricingInputsChanged;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\NearExpiry;
use App\Modules\Catalog\Support\VariantPricing;
use App\Modules\Catalog\Support\VariantSku;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a variant: checks the price inputs (VariantPricing), generates a missing SKU, sets the
 * near-expiry flag from the expiry date, and keeps an active product sellable (it must keep an active variant).
 *
 * The selling price and the stock are not inputs (roadmap D3.2): Pricing sets both, from the active supplier or
 * else the printed price. A new SKU starts at 0 and out of stock until Pricing has priced it. A new SKU, or a
 * changed printed price or haraj, is announced (PricingInputsChanged) so Pricing re-prices it in the background.
 */
final class SaveProductVariant
{
    /**
     * @throws ProductChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Product $product, ProductVariantData $data, ?ProductVariant $variant = null): ProductVariant
    {
        VariantPricing::assertValid($product, $data, $variant);

        if ($variant instanceof ProductVariant && $variant->isActive() && ! $data->isActive) {
            DeleteProductVariant::assertNotLastActive($product, $variant);
        }

        $saved = DB::transaction(function () use ($product, $data, $variant): ProductVariant {
            $variant ??= (new ProductVariant)
                ->setProductId($product->getId())
                ->setSort((int) $product->variants()->max(ProductVariant::COLUMN_SORT) + 1)
                ->setPrice(0);
            $isNew = ! $variant->exists;
            $stock = $isNew ? StockStatus::OutOfStock : $variant->getStockStatus();

            $variant
                ->setSku($data->sku ?? ($variant->exists ? $variant->getSku() : VariantSku::next($product->loadMissing('brand'))))
                ->setBarcode($data->barcode)
                ->setTitleFa($data->titleFa)
                ->setOfficialPrice($data->officialPrice)
                ->setSalePrice($data->salePrice)
                ->setStockStatus($stock)
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
                ->setIsActive($data->isActive);

            $change = match (true) {
                $isNew => PricingInputChange::Created,
                $variant->isDirty(ProductVariant::COLUMN_OFFICIAL_PRICE) => PricingInputChange::Printed,
                $variant->isDirty(ProductVariant::COLUMN_SALE_PRICE) => PricingInputChange::Haraj,
                default => null,
            };

            $variant->save();

            if ($change !== null) {
                event(new PricingInputsChanged([$variant->getId()], $change, UpdateProduct::adminId()));
            }

            return $variant;
        });

        ProductSearchIndex::refresh($product->unsetRelation('variants'));

        return $saved;
    }
}
