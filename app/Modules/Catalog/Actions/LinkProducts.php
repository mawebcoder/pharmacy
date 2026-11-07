<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductRelation;

/**
 * Links a product to another one as related, alternative or frequently bought together. Linking the same pair
 * with the same type twice keeps one link.
 */
final class LinkProducts
{
    /**
     * @throws ProductChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Product $product, int $relatedProductId, ProductRelationType $type): ProductRelation
    {
        if ($relatedProductId === $product->getId()) {
            throw ProductChangeRefused::relatedToItself();
        }

        $relation = ProductRelation::query()
            ->where(ProductRelation::COLUMN_PRODUCT_ID, $product->getId())
            ->where(ProductRelation::COLUMN_RELATED_PRODUCT_ID, $relatedProductId)
            ->where(ProductRelation::COLUMN_TYPE, $type)
            ->first();

        if ($relation instanceof ProductRelation) {
            return $relation;
        }

        $relation = (new ProductRelation)
            ->setProductId($product->getId())
            ->setRelatedProductId($relatedProductId)
            ->setType($type);
        $relation->save();

        return $relation;
    }
}
