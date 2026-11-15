<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\StoreSubcategoryRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
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
    'slug with spaces' => [[Category::COLUMN_SLUG => 'food supplements'], Category::COLUMN_SLUG],
]);

it('checks a subcategory: a Persian name, a clean slug unused by another subcategory', function (array $input, ?string $field): void {
    Subcategory::factory()->create([Subcategory::COLUMN_SLUG => 'vitamins']);

    $errors = Validator::make([Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها', ...$input], StoreSubcategoryRequest::baseRules())->errors();

    expect($field === null ? $errors->isEmpty() : $errors->has($field))->toBeTrue();
})->with([
    'valid' => [[], null],
    'missing Persian name' => [[Subcategory::COLUMN_NAME_FA => null], Subcategory::COLUMN_NAME_FA],
    'slug with spaces' => [[Subcategory::COLUMN_SLUG => 'vitamin d'], Subcategory::COLUMN_SLUG],
    'slug in use' => [[Subcategory::COLUMN_SLUG => 'vitamins'], Subcategory::COLUMN_SLUG],
]);

it('rejects a slug another category uses, even a deleted one', function (): void {
    Category::factory()->create([Category::COLUMN_SLUG => 'supplements'])->delete();

    $validator = Validator::make(categoryInput([Category::COLUMN_SLUG => 'supplements']), StoreCategoryRequest::baseRules());

    expect($validator->errors()->has(Category::COLUMN_SLUG))->toBeTrue();
});
