<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogSlug;
use App\Modules\Catalog\Support\CategoryPlacement;

/**
 * Updates a category. A new parent puts it (with its subcategories) at the end of the new siblings. The slug only
 * changes when an admin types a new one, so existing links keep working.
 */
final class UpdateCategory
{
    /**
     * @throws CategoryChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(Category $category, CategoryData $data): Category
    {
        $parentChanged = $data->parentId !== $category->getParentId();

        if ($parentChanged) {
            CategoryPlacement::assertAllowed([$category->getId() => $data->parentId]);
            $category->setParentId($data->parentId)->setSort(CreateCategory::nextSort($data->parentId));
        }

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

        return $category;
    }
}
