<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateBrand;
use App\Modules\Catalog\Actions\DeleteBrand;
use App\Modules\Catalog\Actions\UpdateBrand;
use App\Modules\Catalog\Data\BrandData;
use App\Modules\Catalog\Exceptions\BrandHasProducts;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;

function brandData(array $overrides = []): BrandData
{
    return BrandData::fromArray([
        Brand::COLUMN_NAME_FA => 'اوسرین',
        Brand::COLUMN_NAME_EN => 'Eucerin',
        Brand::COLUMN_COUNTRY => 'de',
        Brand::COLUMN_IS_ACTIVE => true,
        ...$overrides,
    ]);
}

it('builds the slug from the English name', function (): void {
    $brand = app(CreateBrand::class)(brandData([Brand::COLUMN_NAME_EN => 'La Roche Posay']));

    expect($brand->getSlug())->toBe('la-roche-posay')
        ->and($brand->getCountry())->toBe('DE');
});

it('falls back to the Persian name when there is no English name', function (): void {
    $brand = app(CreateBrand::class)(brandData([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_NAME_EN => null]));

    expect($brand->getSlug())->toBe('سینره');
});

it('keeps the slug of a deleted brand reserved', function (): void {
    Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin'])->delete();

    $brand = app(CreateBrand::class)(brandData());

    expect($brand->getSlug())->not->toBe('eucerin')
        ->and($brand->getSlug())->toStartWith('eucerin-');
});

it('keeps the slug when a brand is renamed', function (): void {
    $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);

    $updated = app(UpdateBrand::class)($brand, brandData([Brand::COLUMN_NAME_EN => 'Eucerin Germany', Brand::COLUMN_SLUG => null]));

    expect($updated->fresh()->getSlug())->toBe('eucerin')
        ->and($updated->fresh()->getNameEn())->toBe('Eucerin Germany');
});

it('changes the slug when an admin types a new one', function (): void {
    $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);

    $updated = app(UpdateBrand::class)($brand, brandData([Brand::COLUMN_SLUG => 'Eucerin-DE']));

    expect($updated->fresh()->getSlug())->toBe('eucerin-de');
});

it('moves a brand without products to the trash', function (): void {
    $brand = Brand::factory()->create();

    app(DeleteBrand::class)($brand);

    expect($brand->fresh()->trashed())->toBeTrue();
});

it('refuses to delete a brand that has products', function (): void {
    $brand = Brand::factory()->create();
    Product::factory()->for($brand)->create();

    expect(fn () => app(DeleteBrand::class)($brand))->toThrow(BrandHasProducts::class);

    expect($brand->fresh()->trashed())->toBeFalse();
});

it('lets a brand go once its products are deleted', function (): void {
    $brand = Brand::factory()->create();
    Product::factory()->for($brand)->create()->delete();

    app(DeleteBrand::class)($brand);

    expect($brand->fresh()->trashed())->toBeTrue();
});
