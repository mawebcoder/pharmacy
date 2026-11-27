<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * Why a product is not on the storefront, in the admin's words (owner, 2026-11-27): the same rules as
 * Product::scopeActive(), scopeSellable() and isShownOnStorefront(). Shown at the top of the product form.
 */
final class ProductVisibility
{
    public const string RULES = 'برای نمایش در فهرست‌ها، جستجو و صفحه اصلی، محصول باید وضعیت «فعال»، برند، زیردسته اصلی و دست‌کم یک تنوع فعال داشته باشد و داروی نسخه‌ای نباشد. داروی نسخه‌ای فقط با پیوند مستقیم باز می‌شود و «فروش آنلاین به‌زودی» نشان می‌دهد. کالای ناموجود نمایش داده می‌شود ولی خریدنی نیست؛ تنوع غیرفعال در سایت دیده نمی‌شود.';

    /**
     * What keeps this product off the storefront lists and search; empty when it is shown.
     *
     * @return list<string>
     */
    public static function problems(Product $product): array
    {
        $problems = [];

        if ($product->trashed()) {
            $problems[] = 'حذف شده است.';
        }

        if ($product->getStatus() !== ProductStatus::Active) {
            $problems[] = 'وضعیت «'.$product->getStatus()->label().'» است؛ باید «فعال» باشد.';
        }

        if ($product->getBrandId() === null) {
            $problems[] = 'برند ندارد.';
        }

        if ($product->getPrimarySubcategoryId() === null) {
            $problems[] = 'زیردسته اصلی ندارد.';
        }

        if ($product->requiresPrescription()) {
            $problems[] = 'داروی نسخه‌ای است: در فهرست‌ها و جستجو نیست و صفحه‌اش «فروش آنلاین به‌زودی» نشان می‌دهد.';
        } elseif (! $product->isSellableType()) {
            $problems[] = 'نوع «'.$product->getProductType()->label().'» فعلاً در سایت فروخته نمی‌شود.';
        }

        if (! $product->variants()->where(ProductVariant::COLUMN_IS_ACTIVE, true)->exists()) {
            $problems[] = 'هیچ تنوع فعالی ندارد.';
        }

        return $problems;
    }
}
