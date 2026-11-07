<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Data\ProductVariantData;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Support\Toman;

/**
 * Price rules of a variant (roadmap D3.1, D3.2): the sale price is below the price; no product is sold above the
 * price printed on its box (decided 2026-11-07, S12; for medicines it is also the legal cap), and a medicine (OTC/Rx)
 * must have that price.
 */
final class VariantPricing
{
    /**
     * @throws ProductChangeRefused
     */
    public static function assertValid(Product $product, ProductVariantData $data): void
    {
        if ($data->salePrice !== null && $data->salePrice >= $data->price) {
            throw ProductChangeRefused::salePriceNotBelowPrice();
        }

        if ($product->getProductType()->isMedicine() && ($data->officialPrice === null || $data->officialPrice === 0)) {
            throw ProductChangeRefused::officialPriceRequired();
        }

        if ($data->officialPrice !== null && $data->officialPrice > 0 && $data->price > $data->officialPrice) {
            throw ProductChangeRefused::aboveOfficialPrice(Toman::fromRial($data->officialPrice));
        }
    }
}
