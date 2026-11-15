<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogSlug;

/**
 * Adds a category at the end of the list (categories are one level; subcategories are Subcategory rows).
 */
final class CreateCategory
{
    #[\NoDiscard]
    public function __invoke(CategoryData $data): Category
    {
        $category = (new Category)
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug(CatalogSlug::for(Category::class, $data->slug, $data->nameFa, $data->nameEn))
            ->setDescription($data->description)
            ->setSort(self::nextSort())
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription);

        $category->save();

        return $category;
    }

    /**
     * The position after the last category.
     */
    public static function nextSort(): int
    {
        return (int) Category::query()->max(Category::COLUMN_SORT) + 1;
    }
}
