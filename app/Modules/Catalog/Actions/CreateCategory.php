<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogSlug;
use App\Modules\Catalog\Support\CategoryPlacement;

/**
 * Adds a category at the end of its siblings.
 */
final class CreateCategory
{
    /**
     * @throws CategoryChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(CategoryData $data): Category
    {
        CategoryPlacement::assertAllowed(
            [CategoryPlacement::NEW_CATEGORY => $data->parentId],
            [CategoryPlacement::NEW_CATEGORY => $data->nameFa],
        );

        $category = (new Category)
            ->setParentId($data->parentId)
            ->setNameFa($data->nameFa)
            ->setNameEn($data->nameEn)
            ->setSlug(CatalogSlug::for(Category::class, $data->slug, $data->nameFa, $data->nameEn))
            ->setDescription($data->description)
            ->setSort(self::nextSort($data->parentId))
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription);

        $category->save();

        return $category;
    }

    /**
     * The position after the last sibling under $parentId.
     */
    public static function nextSort(?int $parentId): int
    {
        return (int) Category::query()->where(Category::COLUMN_PARENT_ID, $parentId)->max(Category::COLUMN_SORT) + 1;
    }
}
