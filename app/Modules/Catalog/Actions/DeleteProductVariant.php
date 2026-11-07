<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Search\ProductSearchIndex;

/**
 * Moves a variant to the trash, unless it is the last active variant of an active product.
 */
final class DeleteProductVariant
{
    /**
     * @throws ProductChangeRefused
     */
    public function __invoke(ProductVariant $variant): void
    {
        $product = $variant->getProduct();

        if ($product instanceof Product && $variant->isActive()) {
            self::assertNotLastActive($product, $variant);
        }

        $variant->delete();

        if ($product instanceof Product) {
            ProductSearchIndex::refresh($product);
        }
    }

    /**
     * @throws ProductChangeRefused
     */
    public static function assertNotLastActive(Product $product, ProductVariant $variant): void
    {
        $othersActive = $product->variants()->active()->whereKeyNot($variant->getId())->exists();

        if ($product->isActive() && ! $othersActive) {
            throw ProductChangeRefused::lastActiveVariant();
        }
    }
}
