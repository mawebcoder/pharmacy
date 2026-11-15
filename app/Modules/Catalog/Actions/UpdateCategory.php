<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\CatalogSlug;

/**
 * Updates a category. The slug only changes when an admin types a new one, so existing links keep working.
 */
final class UpdateCategory
{
    #[\NoDiscard]
    public function __invoke(Category $category, CategoryData $data): Category
    {
        $slug = $data->slug === null || $data->slug === $category->getSlug()
            ? $category->getSlug()
            : CatalogSlug::for(Category::class, $data->slug, $data->nameFa, $data->nameEn, $category->getId());

        $category
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setDescription($data->description)
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription)
            ->save();

        ProductSearchIndex::refreshCategory($category);

        return $category;
    }
}
