<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;

/**
 * Brings a category back from the trash.
 */
final class RestoreCategory
{
    public function __invoke(Category $category): void
    {
        $category->restore();
    }
}
