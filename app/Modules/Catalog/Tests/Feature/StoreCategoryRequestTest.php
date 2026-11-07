<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\Validator;

function categoryInput(array $overrides = []): array
{
    return [Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_IS_ACTIVE => true, ...$overrides];
}

it('accepts a top-level category with only a Persian name', function (): void {
    expect(Validator::make(categoryInput(), StoreCategoryRequest::baseRules())->passes())->toBeTrue();
});

it('rejects invalid input', function (array $input, string $field): void {
    expect(Validator::make(categoryInput($input), StoreCategoryRequest::baseRules())->errors()->has($field))->toBeTrue();
})->with([
    'missing Persian name' => [[Category::COLUMN_NAME_FA => null], Category::COLUMN_NAME_FA],
    'unknown parent' => [[Category::COLUMN_PARENT_ID => 999_999], Category::COLUMN_PARENT_ID],
    'slug with spaces' => [[Category::COLUMN_SLUG => 'food supplements'], Category::COLUMN_SLUG],
]);

it('rejects a deleted parent', function (): void {
    $parent = Category::factory()->create();
    $parent->delete();

    $validator = Validator::make(categoryInput([Category::COLUMN_PARENT_ID => $parent->getId()]), StoreCategoryRequest::baseRules());

    expect($validator->errors()->has(Category::COLUMN_PARENT_ID))->toBeTrue();
});

it('rejects a category as its own parent', function (): void {
    $category = Category::factory()->create();

    $validator = Validator::make(categoryInput([Category::COLUMN_PARENT_ID => $category->getId()]), StoreCategoryRequest::baseRules($category->getId()));

    expect($validator->errors()->has(Category::COLUMN_PARENT_ID))->toBeTrue();
});

it('rejects a slug another category uses, even a deleted one', function (): void {
    Category::factory()->create([Category::COLUMN_SLUG => 'supplements'])->delete();

    $validator = Validator::make(categoryInput([Category::COLUMN_SLUG => 'supplements']), StoreCategoryRequest::baseRules());

    expect($validator->errors()->has(Category::COLUMN_SLUG))->toBeTrue();
});
