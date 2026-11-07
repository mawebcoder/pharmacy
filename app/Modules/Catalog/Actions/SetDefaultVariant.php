<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Makes a variant the product's default SKU (shown on cards and in search, added by «افزودن به سبد»). While it is out
 * of stock the next in-stock variant stands in for it (Product::getDefaultVariant()). Saving the product refreshes
 * its search document.
 */
final class SetDefaultVariant
{
    /**
     * @throws ProductChangeRefused
     */
    public function __invoke(Product $product, ProductVariant $variant): void
    {
        if ($variant->getProductId() !== $product->getId() || ! $variant->isActive()) {
            throw ProductChangeRefused::defaultVariantNotUsable();
        }

        $product->setDefaultVariantId($variant->getId())->save();
    }
}
