<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Subcategory;

/**
 * Moves a subcategory to the trash, unless products still use it (main or extra subcategory).
 */
final class DeleteSubcategory
{
    /**
     * @throws CategoryChangeRefused
     */
    public function __invoke(Subcategory $subcategory): void
    {
        if ($subcategory->hasProducts()) {
            throw CategoryChangeRefused::subcategoryHasProducts($subcategory->getNameFa());
        }

        $subcategory->delete();
    }
}
