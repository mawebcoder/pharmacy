<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Generated SKUs: brand code, product number and variant number, e.g. "EUC-000007-02". Unique among all variants,
 * including deleted ones.
 */
final class VariantSku
{
    public static function next(Product $product): string
    {
        $code = Str::upper(Str::substr((string) preg_replace('/[^A-Za-z]/', '', (string) $product->getBrand()?->getNameEn()), 0, 3)) ?: 'PRD';
        $prefix = sprintf('%s-%06d-', $code, $product->getId());
        $number = ProductVariant::withTrashed()->where(ProductVariant::COLUMN_PRODUCT_ID, $product->getId())->count() + 1;

        while (ProductVariant::withTrashed()->where(ProductVariant::COLUMN_SKU, $prefix.sprintf('%02d', $number))->exists()) {
            $number++;
        }

        return $prefix.sprintf('%02d', $number);
    }
}
