<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\VariantPricingInputs;

/**
 * Price rules of a variant's admin inputs (roadmap D3.1, D3.2). The selling price itself is worked out by Pricing,
 * which also keeps it at or below the printed price (for medicines the legal cap).
 */
final class VariantPricing
{
    /**
     * The admin's price inputs of a SKU (roadmap D3.2): the product or its brand must have a commission (not yet for a
     * product imported without a brand, owner 2026-11-27: it cannot be active or priced until staff set one), medicines
     * need a printed price, and a haraj percentage (0–90; owner, 2026-11-17) needs a selling price to apply to (a new
     * SKU has none yet).
     *
     * @throws ProductChangeRefused
     */
    public static function assertValid(Product $product, ProductVariantData $data, ?ProductVariant $variant = null): void
    {
        if ($product->getBrandId() !== null && app(VariantPricingInputs::class)->commissionOf($product) === null) {
            throw ProductChangeRefused::commissionMissing();
        }

        if ($product->getProductType()->isMedicine() && ($data->officialPrice === null || $data->officialPrice === 0)) {
            throw ProductChangeRefused::officialPriceRequired();
        }

        $price = $variant?->getPrice() ?? 0;

        if ($data->harajPercent !== null && $data->harajPercent > 0 && $price === 0) {
            throw ProductChangeRefused::harajNeedsPrice();
        }

        if ($data->harajPercent !== null && ($data->harajPercent < 0 || $data->harajPercent > 90)) {
            throw ProductChangeRefused::harajPercentOutOfRange();
        }
    }
}
