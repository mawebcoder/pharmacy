<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Catalog\Support\CatalogSlug;

/**
 * Adds a subcategory at the end of its category's list, or updates one (owner, 2026-11-15). The slug only changes
 * when an admin types a new one, so existing links keep working. After an update its products are sent to search
 * again (their category names change).
 */
final class SaveSubcategory
{
    #[\NoDiscard]
    public function __invoke(Category $category, SubcategoryData $data, ?Subcategory $subcategory = null): Subcategory
    {
        $isNew = ! $subcategory instanceof Subcategory;
        $subcategory ??= (new Subcategory)
            ->setCategoryId($category->getId())
            ->setSort((int) Subcategory::query()->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId())->max(Subcategory::COLUMN_SORT) + 1);

        $slug = ! $isNew && ($data->slug === null || $data->slug === $subcategory->getSlug())
            ? $subcategory->getSlug()
            : CatalogSlug::for(Subcategory::class, $data->slug, $data->nameFa, $data->nameEn, $isNew ? null : $subcategory->getId());

        $subcategory
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug($slug)
            ->setDescription($data->description)
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription)
            ->save();

        if (! $isNew) {
            ProductSearchIndex::refreshSubcategory($subcategory);
        }

        return $subcategory;
    }
}
