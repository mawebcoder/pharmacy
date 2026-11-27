<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Events\CategorySearchFieldsChanged;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
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

        // Only the name is in its products' documents.
        // Its subcategories' search documents carry its slug (their sitemap addresses).
        if ($category->wasChanged(Category::COLUMN_SLUG)) {
            $category->subcategories()->get()->each(fn (Subcategory $subcategory) => $subcategory->searchable());
        }

        if ($category->wasChanged([Category::COLUMN_NAME_FA, Category::COLUMN_NAME_EN])) {
            CategorySearchFieldsChanged::dispatch($category->getId());
        }

        return $category;
    }
}
