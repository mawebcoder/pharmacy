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
     * The admin's price inputs of a SKU (roadmap D3.2): the product or its brand must have a commission, medicines
     * need a printed price, and a haraj must be below the SKU's current selling price (a new SKU has none yet).
     *
     * @throws ProductChangeRefused
     */
    public static function assertValid(Product $product, ProductVariantData $data, ?ProductVariant $variant = null): void
    {
        if (app(VariantPricingInputs::class)->commissionOf($product) === null) {
            throw ProductChangeRefused::commissionMissing();
        }

        if ($product->getProductType()->isMedicine() && ($data->officialPrice === null || $data->officialPrice === 0)) {
            throw ProductChangeRefused::officialPriceRequired();
        }

        $price = $variant?->getPrice() ?? 0;

        if ($data->salePrice !== null && $price === 0) {
            throw ProductChangeRefused::harajNeedsPrice();
        }

        if ($data->salePrice !== null && $data->salePrice >= $price) {
            throw ProductChangeRefused::salePriceNotBelowPrice();
        }
    }
}
