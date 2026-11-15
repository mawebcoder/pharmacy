<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\DeleteSubcategory;
use App\Modules\Catalog\Actions\MoveCategories;
use App\Modules\Catalog\Actions\SaveSubcategory;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;

function categoryData(array $overrides = []): CategoryData
{
    return CategoryData::fromArray([
        Category::COLUMN_NAME_FA => 'مکمل‌ها',
        Category::COLUMN_NAME_EN => 'Supplements',
        Category::COLUMN_IS_ACTIVE => true,
        ...$overrides,
    ]);
}

function subcategoryData(array $overrides = []): SubcategoryData
{
    return SubcategoryData::fromArray([
        Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها',
        Subcategory::COLUMN_NAME_EN => 'Vitamins',
        Subcategory::COLUMN_IS_ACTIVE => true,
        ...$overrides,
    ]);
}

describe('creating', function (): void {
    it('builds the slug from the English name, else the Persian name', function (): void {
        $english = app(CreateCategory::class)(categoryData([Category::COLUMN_NAME_EN => 'Vitamins & Minerals']));
        $persian = app(CreateCategory::class)(categoryData([Category::COLUMN_NAME_FA => 'مادر و کودک', Category::COLUMN_NAME_EN => null]));

        expect($english->getSlug())->toBe('vitamins-minerals')
            ->and($persian->getSlug())->toBe('مادر-و-کودک');
    });

    it('adds a category at the end of the list', function (): void {
        Category::factory()->create([Category::COLUMN_SORT => 4]);

        $category = app(CreateCategory::class)(categoryData());

        expect($category->getSort())->toBe(5);
    });
});

describe('updating', function (): void {
    it('keeps the slug when a category is renamed', function (): void {
        $category = Category::factory()->create([Category::COLUMN_SLUG => 'supplements']);

        $renamed = app(UpdateCategory::class)($category, categoryData([Category::COLUMN_NAME_EN => 'Food Supplements']));

        expect($renamed->fresh()->getSlug())->toBe('supplements');
    });
});

describe('deleting', function (): void {
    it('refuses to delete a category that has subcategories', function (): void {
        $category = Category::factory()->create();
        Subcategory::factory()->of($category)->create();

        expect(fn () => app(DeleteCategory::class)($category))->toThrow(CategoryChangeRefused::class);

        expect($category->fresh()->trashed())->toBeFalse();
    });

    it('moves a category without subcategories or products to the trash', function (): void {
        $category = Category::factory()->create();

        app(DeleteCategory::class)($category);

        expect($category->fresh()->trashed())->toBeTrue();
    });

    it('refuses to delete a category that has products, as primary or extra category', function (Closure $attach): void {
        $category = Category::factory()->create();
        $attach($category);

        expect(fn () => app(DeleteCategory::class)($category))->toThrow(CategoryChangeRefused::class);

        expect($category->fresh()->trashed())->toBeFalse();
    })->with([
        'primary category' => [fn (Category $category) => Product::factory()->for($category, 'primaryCategory')->create()],
        'extra category' => [fn (Category $category) => Product::factory()->create()->categories()->attach($category)],
    ]);
});

describe('reordering', function (): void {
    it('saves the new order from the table', function (): void {
        $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
        $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
        $third = Category::factory()->create([Category::COLUMN_SORT => 3]);

        $moved = app(MoveCategories::class)(CategoryPositionData::fromOrderedIds([$third->getId(), $first->getId(), $second->getId()]));

        expect($moved)->toBe(3)
            ->and($third->fresh()->getSort())->toBe(1)
            ->and($first->fresh()->getSort())->toBe(2)
            ->and($second->fresh()->getSort())->toBe(3);
    });
});

describe('subcategories', function (): void {
    it('adds a subcategory at the end of its own category', function (): void {
        $supplements = Category::factory()->create();
        Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_SORT => 3]);
        Subcategory::factory()->create([Subcategory::COLUMN_SORT => 9]);

        $subcategory = app(SaveSubcategory::class)($supplements, subcategoryData());

        expect($subcategory->fresh())
            ->getCategoryId()->toBe($supplements->getId())
            ->getSort()->toBe(4)
            ->getSlug()->toBe('vitamins');
    });

    it('keeps the slug when a subcategory is renamed', function (): void {
        $subcategory = Subcategory::factory()->create([Subcategory::COLUMN_SLUG => 'vitamins']);

        $renamed = app(SaveSubcategory::class)($subcategory->category, subcategoryData([Subcategory::COLUMN_NAME_EN => 'Vitamins & Minerals']), $subcategory);

        expect($renamed->fresh())
            ->getNameEn()->toBe('Vitamins & Minerals')
            ->getSlug()->toBe('vitamins');
    });

    it('refuses to delete a subcategory that has products, as main or extra subcategory', function (Closure $attach): void {
        $subcategory = Subcategory::factory()->create();
        $attach($subcategory);

        expect(fn () => app(DeleteSubcategory::class)($subcategory))->toThrow(CategoryChangeRefused::class);

        expect($subcategory->fresh()->trashed())->toBeFalse();
    })->with([
        'main subcategory' => [fn (Subcategory $subcategory) => Product::factory()->for($subcategory, 'primarySubcategory')->create()],
        'extra subcategory' => [fn (Subcategory $subcategory) => Product::factory()->create()->subcategories()->attach($subcategory)],
    ]);

    it('moves a subcategory without products to the trash', function (): void {
        $subcategory = Subcategory::factory()->create();

        app(DeleteSubcategory::class)($subcategory);

        expect($subcategory->fresh()->trashed())->toBeTrue();
    });
});
