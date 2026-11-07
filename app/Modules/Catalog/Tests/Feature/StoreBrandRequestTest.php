<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Requests\StoreBrandRequest;
use App\Modules\Catalog\Models\Brand;
use Illuminate\Support\Facades\Validator;

function brandInput(array $overrides = []): array
{
    return [Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin', Brand::COLUMN_COUNTRY => 'DE', Brand::COLUMN_IS_ACTIVE => true, ...$overrides];
}

it('accepts a brand with only a Persian name', function (): void {
    expect(Validator::make([Brand::COLUMN_NAME_FA => 'اوسرین'], StoreBrandRequest::baseRules())->passes())->toBeTrue();
});

it('rejects invalid input', function (array $input, string $field): void {
    expect(Validator::make(brandInput($input), StoreBrandRequest::baseRules())->errors()->has($field))->toBeTrue();
})->with([
    'missing Persian name' => [[Brand::COLUMN_NAME_FA => null], Brand::COLUMN_NAME_FA],
    'unknown country' => [[Brand::COLUMN_COUNTRY => 'XX'], Brand::COLUMN_COUNTRY],
    'slug with spaces' => [[Brand::COLUMN_SLUG => 'eu cerin'], Brand::COLUMN_SLUG],
    'slug with a slash' => [[Brand::COLUMN_SLUG => 'eu/cerin'], Brand::COLUMN_SLUG],
    'long meta description' => [[Brand::COLUMN_SEO_DESCRIPTION => str_repeat('ا', 501)], Brand::COLUMN_SEO_DESCRIPTION],
]);

it('rejects a slug another brand uses, even a deleted one', function (): void {
    Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin'])->delete();

    $validator = Validator::make(brandInput([Brand::COLUMN_SLUG => 'eucerin']), StoreBrandRequest::baseRules());

    expect($validator->errors()->has(Brand::COLUMN_SLUG))->toBeTrue();
});

it('lets a brand keep its own slug on update', function (): void {
    $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);

    $validator = Validator::make(brandInput([Brand::COLUMN_SLUG => 'eucerin']), StoreBrandRequest::baseRules($brand->getId()));

    expect($validator->passes())->toBeTrue();
});
