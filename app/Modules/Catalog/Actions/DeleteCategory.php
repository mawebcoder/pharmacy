<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;

/**
 * Moves a category to the trash, unless it still has subcategories or products.
 */
final class DeleteCategory
{
    /**
     * @throws CategoryChangeRefused
     */
    public function __invoke(Category $category): void
    {
        if ($category->children()->exists()) {
            throw CategoryChangeRefused::hasChildren($category->getNameFa());
        }

        if ($category->hasProducts()) {
            throw CategoryChangeRefused::hasProducts($category->getNameFa());
        }

        $category->delete();
    }
}
