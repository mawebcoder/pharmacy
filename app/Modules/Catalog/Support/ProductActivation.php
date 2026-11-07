<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Exceptions\ProductChangeRefused;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;

/**
 * What a product needs before it can be active (for sale), roadmap S10: a brand and primary category that are not
 * deleted, an active variant with a price, a photo, and a type that may be sold (no prescription medicines).
 */
final class ProductActivation
{
    /**
     * @return list<string> what is missing, in Persian; empty when the product may be active
     */
    public static function missing(Product $product): array
    {
        $missing = [];

        if (! $product->isSellableType()) {
            $missing[] = 'فروش «'.$product->getProductType()->label().'» و داروهای نیازمند نسخه فعلاً امکان‌پذیر نیست';
        }

        if (! $product->getBrand() instanceof Brand || $product->getBrand()->trashed()) {
            $missing[] = 'برند مشخص نیست یا حذف شده است';
        }

        if (! $product->getPrimaryCategory() instanceof Category || $product->getPrimaryCategory()->trashed()) {
            $missing[] = 'دسته اصلی مشخص نیست یا حذف شده است';
        }

        if (! $product->variants()->active()->where('price', '>', 0)->exists()) {
            $missing[] = 'دست‌کم یک تنوع فعال با قیمت لازم است';
        }

        if ($product->getMedia(Product::IMAGE_GALLERY)->isEmpty()) {
            $missing[] = 'دست‌کم یک تصویر لازم است';
        }

        return $missing;
    }

    /**
     * @throws ProductChangeRefused
     */
    public static function assertAllowed(Product $product): void
    {
        $missing = self::missing($product->loadMissing(['brand', 'primaryCategory', 'media']));

        if ($missing !== []) {
            throw ProductChangeRefused::cannotActivate($missing);
        }
    }
}
