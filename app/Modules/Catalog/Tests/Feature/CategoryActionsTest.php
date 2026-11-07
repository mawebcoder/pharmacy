<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\MoveCategories;
use App\Modules\Catalog\Actions\RestoreCategory;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Models\Category;

function categoryData(array $overrides = []): CategoryData
{
    return CategoryData::fromArray([
        Category::COLUMN_NAME_FA => 'مکمل‌ها',
        Category::COLUMN_NAME_EN => 'Supplements',
        Category::COLUMN_IS_ACTIVE => true,
        ...$overrides,
    ]);
}

/**
 * مکمل‌ها › ویتامین‌ها › ویتامین D
 *
 * @return array{0: Category, 1: Category, 2: Category}
 */
function threeLevels(): array
{
    $root = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
    $middle = Category::factory()->childOf($root)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    $leaf = Category::factory()->childOf($middle)->create([Category::COLUMN_NAME_FA => 'ویتامین D']);

    return [$root, $middle, $leaf];
}

describe('creating', function (): void {
    it('builds the slug from the English name, else the Persian name', function (): void {
        $english = app(CreateCategory::class)(categoryData([Category::COLUMN_NAME_EN => 'Vitamins & Minerals']));
        $persian = app(CreateCategory::class)(categoryData([Category::COLUMN_NAME_FA => 'مادر و کودک', Category::COLUMN_NAME_EN => null]));

        expect($english->getSlug())->toBe('vitamins-minerals')
            ->and($persian->getSlug())->toBe('مادر-و-کودک');
    });

    it('adds a category after its last sibling', function (): void {
        $root = Category::factory()->create();
        Category::factory()->childOf($root)->create([Category::COLUMN_SORT => 4]);

        $category = app(CreateCategory::class)(categoryData([Category::COLUMN_PARENT_ID => $root->getId()]));

        expect($category->getSort())->toBe(5);
    });

    it('refuses a fourth level', function (): void {
        [, , $leaf] = threeLevels();

        expect(fn () => app(CreateCategory::class)(categoryData([Category::COLUMN_PARENT_ID => $leaf->getId()])))
            ->toThrow(CategoryChangeRefused::class);

        expect(Category::query()->count())->toBe(3);
    });
});

describe('updating', function (): void {
    it('moves a category with its subcategories to the end of the new siblings', function (): void {
        [$root, $middle, $leaf] = threeLevels();
        $other = Category::factory()->create();
        Category::factory()->childOf($other)->create([Category::COLUMN_SORT => 1]);

        $moved = app(UpdateCategory::class)($middle, categoryData([Category::COLUMN_NAME_FA => 'ویتامین‌ها', Category::COLUMN_PARENT_ID => $other->getId()]));

        expect($moved->fresh())
            ->getParentId()->toBe($other->getId())
            ->getSort()->toBe(2)
            ->and($leaf->fresh()->getParentId())->toBe($middle->getId());
    });

    it('refuses to put a category under its own subcategory', function (): void {
        [$root, , $leaf] = threeLevels();

        expect(fn () => app(UpdateCategory::class)($root, categoryData([Category::COLUMN_PARENT_ID => $leaf->getId()])))
            ->toThrow(CategoryChangeRefused::class);

        expect($root->fresh()->getParentId())->toBeNull();
    });

    it('refuses a move that pushes subcategories past three levels', function (): void {
        [, $middle] = threeLevels();
        $otherRoot = Category::factory()->create();

        expect(fn () => app(UpdateCategory::class)($middle, categoryData([Category::COLUMN_PARENT_ID => Category::factory()->childOf($otherRoot)->create()->getId()])))
            ->toThrow(CategoryChangeRefused::class);
    });

    it('keeps the slug when a category is renamed', function (): void {
        $category = Category::factory()->create([Category::COLUMN_SLUG => 'supplements']);

        $renamed = app(UpdateCategory::class)($category, categoryData([Category::COLUMN_NAME_EN => 'Food Supplements']));

        expect($renamed->fresh()->getSlug())->toBe('supplements');
    });
});

describe('deleting and restoring', function (): void {
    it('refuses to delete a category that has subcategories', function (): void {
        [$root] = threeLevels();

        expect(fn () => app(DeleteCategory::class)($root))->toThrow(CategoryChangeRefused::class);

        expect($root->fresh()->trashed())->toBeFalse();
    });

    it('moves a category without subcategories to the trash', function (): void {
        [, , $leaf] = threeLevels();

        app(DeleteCategory::class)($leaf);

        expect($leaf->fresh()->trashed())->toBeTrue();
    });

    it('refuses to restore a category whose parent is deleted', function (): void {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();
        $child->delete();
        $root->delete();

        expect(fn () => app(RestoreCategory::class)($child->fresh()))->toThrow(CategoryChangeRefused::class);

        app(RestoreCategory::class)($root->fresh());
        app(RestoreCategory::class)($child->fresh());

        expect($child->fresh()->trashed())->toBeFalse();
    });

    it('refuses to delete a category that has products')->todo('S10: needs products to exist');
});

describe('moving in the tree', function (): void {
    it('saves the new order and nesting from the tree', function (): void {
        $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
        $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
        $third = Category::factory()->create([Category::COLUMN_SORT => 3]);

        $moved = app(MoveCategories::class)(CategoryPositionData::fromNestedList([
            ['id' => $third->getId(), 'children' => [['id' => $first->getId(), 'children' => []]]],
            ['id' => $second->getId(), 'children' => []],
        ]));

        expect($moved)->toBe(2)
            ->and($third->fresh())->getParentId()->toBeNull()->getSort()->toBe(1)
            ->and($first->fresh())->getParentId()->toBe($third->getId())->getSort()->toBe(1)
            ->and($second->fresh()->getSort())->toBe(2);
    });

    it('saves nothing when the tree would get a fourth level', function (): void {
        [$root, $middle, $leaf] = threeLevels();
        $extra = Category::factory()->create();

        expect(fn () => app(MoveCategories::class)(CategoryPositionData::fromNestedList([
            ['id' => $root->getId(), 'children' => [
                ['id' => $middle->getId(), 'children' => [
                    ['id' => $leaf->getId(), 'children' => [['id' => $extra->getId(), 'children' => []]]],
                ]],
            ]],
        ])))->toThrow(CategoryChangeRefused::class);

        expect($extra->fresh()->getParentId())->toBeNull();
    });
});
