<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Models\Category;
use RuntimeException;

/**
 * A category change that would break the tree or leave products or subcategories without a parent.
 */
final class CategoryChangeRefused extends RuntimeException
{
    public static function ownDescendant(string $categoryName): self
    {
        return new self("دسته «{$categoryName}» را نمی‌توان زیر خودش یا زیرمجموعه‌هایش قرار داد.");
    }

    public static function tooDeep(string $categoryName): self
    {
        return new self("با این جابه‌جایی، دسته «{$categoryName}» بیش از ".Category::MAX_DEPTH.' سطح می‌شود.');
    }

    public static function parentMissing(): self
    {
        return new self('دسته والد پیدا نشد یا حذف شده است.');
    }

    public static function hasChildren(string $categoryName): self
    {
        return new self("دسته «{$categoryName}» زیردسته دارد و حذف نمی‌شود. ابتدا زیردسته‌ها را جابه‌جا یا حذف کنید.");
    }

    public static function hasProducts(string $categoryName): self
    {
        return new self("دسته «{$categoryName}» محصول دارد و حذف نمی‌شود. ابتدا محصولات آن را به دسته دیگری منتقل کنید.");
    }

    public static function parentDeleted(string $categoryName): self
    {
        return new self("دسته والدِ «{$categoryName}» حذف شده است. ابتدا آن را بازگردانید.");
    }
}
