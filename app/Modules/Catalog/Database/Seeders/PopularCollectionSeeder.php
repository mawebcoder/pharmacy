<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Database\Seeder;

/**
 * The system collection «محصولات محبوب» (roadmap S14): it always exists and cannot be deleted. Running it again does
 * nothing once it is there.
 */
final class PopularCollectionSeeder extends Seeder
{
    public function run(): void
    {
        if (ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->exists()) {
            return;
        }

        (new ProductCollection)
            ->setNameFa('محصولات محبوب')
            ->setNameEn('Popular')
            ->setSlug(ProductCollection::POPULAR_SLUG)
            ->setIsSystem(true)
            ->setIsActive(true)
            ->save();
    }
}
