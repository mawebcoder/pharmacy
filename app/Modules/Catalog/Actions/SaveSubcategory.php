<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Events\SubcategorySearchFieldsChanged;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Support\CatalogSlug;
use Illuminate\Support\Facades\DB;

/**
 * Adds a subcategory at the end of its category's list, or updates one (owner, 2026-11-15). The slug only changes
 * when an admin types a new one, so existing links keep working. After an update its products are sent to search
 * again (their category names change).
 *
 * Updating it with another category moves it there (owner, 2026-11-28): it goes to the end of that category's list,
 * keeps its slug (unique among all subcategories), and the products whose main subcategory it is follow it to the new
 * main category. Its old address is redirected by the Seo module (RecordSlugRedirects).
 */
final class SaveSubcategory
{
    #[\NoDiscard]
    public function __invoke(Category $category, SubcategoryData $data, ?Subcategory $subcategory = null): Subcategory
    {
        $isNew = ! $subcategory instanceof Subcategory;
        $subcategory ??= (new Subcategory)->setCategoryId($category->getId())->setSort($this->nextSort($category));
        $isMove = ! $isNew && $subcategory->getCategoryId() !== $category->getId();

        if ($isMove && $category->trashed()) {
            throw CategoryChangeRefused::movedToDeletedCategory($subcategory->getNameFa(), $category->getNameFa());
        }

        $slug = ! $isNew && ($data->slug === null || $data->slug === $subcategory->getSlug())
            ? $subcategory->getSlug()
            : CatalogSlug::for(Subcategory::class, $data->slug, $data->nameFa, $data->nameEn, $isNew ? null : $subcategory->getId());

        DB::transaction(function () use ($subcategory, $category, $data, $slug, $isMove): void {
            if ($isMove) {
                $subcategory->setCategoryId($category->getId())->setSort($this->nextSort($category));
            }

            $subcategory
                ->setNameFa($data->nameFa)
                ->setNameEn($data->nameEn)
                ->setSlug($slug)
                ->setDescription($data->description)
                ->setIsActive($data->isActive)
                ->setSeoTitle($data->seoTitle)
                ->setSeoDescription($data->seoDescription)
                ->save();

            // A product's main category is always its main subcategory's category.
            if ($isMove) {
                Product::withTrashed()
                    ->where(Product::COLUMN_PRIMARY_SUBCATEGORY_ID, $subcategory->getId())
                    ->update([Product::COLUMN_PRIMARY_CATEGORY_ID => $category->getId()]);
            }
        });

        // Its name and category are in its products' documents (subcategory_names, category_ids).
        if (! $isNew && $subcategory->wasChanged([Subcategory::COLUMN_NAME_FA, Subcategory::COLUMN_NAME_EN, Subcategory::COLUMN_CATEGORY_ID])) {
            SubcategorySearchFieldsChanged::dispatch($subcategory->getId());
        }

        return $subcategory;
    }

    private function nextSort(Category $category): int
    {
        return (int) Subcategory::query()->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId())->max(Subcategory::COLUMN_SORT) + 1;
    }
}
