<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * A category or subcategory change that would leave products or subcategories without a home.
 */
final class CategoryChangeRefused extends RuntimeException
{
    public static function hasChildren(string $categoryName): self
    {
        return new self("دسته «{$categoryName}» زیردسته دارد و حذف نمی‌شود. ابتدا زیردسته‌هایش را حذف کنید.");
    }

    public static function hasProducts(string $categoryName): self
    {
        return new self("دسته «{$categoryName}» محصول دارد و حذف نمی‌شود. ابتدا محصولات آن را به دسته دیگری منتقل کنید.");
    }

    public static function subcategoryHasProducts(string $subcategoryName): self
    {
        return new self("زیردسته «{$subcategoryName}» محصول دارد و حذف نمی‌شود. ابتدا محصولات آن را منتقل کنید.");
    }

    public static function categoryDeleted(string $subcategoryName): self
    {
        return new self("دستهٔ زیردسته «{$subcategoryName}» حذف شده است. ابتدا دسته را بازگردانید.");
    }

    public static function movedToDeletedCategory(string $subcategoryName, string $categoryName): self
    {
        return new self("زیردسته «{$subcategoryName}» به دستهٔ حذف‌شدهٔ «{$categoryName}» منتقل نمی‌شود. ابتدا دسته را بازگردانید.");
    }
}
