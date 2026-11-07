<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\BrandHasProducts;
use App\Modules\Catalog\Models\Brand;

/**
 * Moves a brand to the trash, unless products still use it.
 */
final class DeleteBrand
{
    /**
     * @throws BrandHasProducts
     */
    public function __invoke(Brand $brand): void
    {
        if ($brand->hasProducts()) {
            throw BrandHasProducts::cannotDelete($brand->getNameFa());
        }

        $brand->delete();
    }
}
