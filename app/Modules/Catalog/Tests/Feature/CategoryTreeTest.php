<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\MoveCategories;
use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Data\CategoryPositionData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Queries\CategoryTree;
use Illuminate\Support\Facades\Cache;

/**
 * @param  list<CategoryNode>  $nodes
 * @return array<string, mixed> name => children (same shape)
 */
function treeNames(array $nodes): array
{
    $names = [];

    foreach ($nodes as $node) {
        $names[$node->nameFa] = treeNames($node->children);
    }

    return $names;
}

it('lists active categories in sort order with their subcategories', function (): void {
    $beauty = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرایشی', Category::COLUMN_SORT => 2]);
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SORT => 1]);
    $vitamins = Category::factory()->childOf($supplements)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Category::factory()->childOf($vitamins)->create([Category::COLUMN_NAME_FA => 'ویتامین D']);
    Category::factory()->childOf($beauty)->inactive()->create([Category::COLUMN_NAME_FA => 'رژ لب']);

    expect(treeNames(app(CategoryTree::class)->roots()))->toBe([
        'مکمل‌ها' => ['ویتامین‌ها' => ['ویتامین D' => []]],
        'آرایشی' => [],
    ]);
});

it('hides the whole branch under an inactive category', function (): void {
    $supplements = Category::factory()->inactive()->create();
    Category::factory()->childOf($supplements)->create();

    expect(app(CategoryTree::class)->roots())->toBe([]);
});

it('rebuilds the cached tree after a change', function (Closure $change, array $expected): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SORT => 1]);
    $beauty = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرایشی', Category::COLUMN_SORT => 2]);
    app(CategoryTree::class)->roots();

    $change($supplements, $beauty);

    expect(array_keys(treeNames(app(CategoryTree::class)->roots())))->toBe($expected);
})->with([
    'renamed' => [fn (Category $supplements) => $supplements->setNameFa('مکمل غذایی')->save(), ['مکمل غذایی', 'آرایشی']],
    'deleted' => [fn (Category $supplements) => $supplements->delete(), ['آرایشی']],
    'restored' => [function (Category $supplements, Category $beauty): void {
        $beauty->delete();
        app(CategoryTree::class)->roots();
        $beauty->restore();
    }, ['مکمل‌ها', 'آرایشی']],
    'reordered' => [fn (Category $supplements, Category $beauty) => app(MoveCategories::class)(CategoryPositionData::fromNestedList([
        ['id' => $beauty->getId()], ['id' => $supplements->getId()],
    ])), ['آرایشی', 'مکمل‌ها']],
]);

it('reads the tree back from a cache that serializes its values', function (): void {
    config(['cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');
    Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);

    app(CategoryTree::class)->roots();

    expect(app(CategoryTree::class)->roots()[0]->nameFa)->toBe('مکمل‌ها');
});
