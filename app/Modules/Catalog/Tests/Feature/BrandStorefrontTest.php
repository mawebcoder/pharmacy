<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;

it('lists only active brands, grouped by letter in Persian alphabet order', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_NAME_EN => 'Cinere']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'آردن', Brand::COLUMN_NAME_EN => 'Arden']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin']);
    Brand::factory()->inactive()->create([Brand::COLUMN_NAME_FA => 'برند غیرفعال']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'برند حذف‌شده'])->delete();

    $this->get(route('brands.index'))
        ->assertOk()
        ->assertSeeInOrder(['الف', 'آردن', 'اوسرین', 'س', 'سینره'])
        ->assertDontSee('برند غیرفعال')
        ->assertDontSee('برند حذف‌شده');
});

it('shows the brand page by slug', function (): void {
    $brand = Brand::factory()->create([
        Brand::COLUMN_NAME_FA => 'اوسرین',
        Brand::COLUMN_SLUG => 'eucerin',
        Brand::COLUMN_COUNTRY => 'DE',
        Brand::COLUMN_SEO_TITLE => 'محصولات اوسرین',
    ]);

    $this->get(route('brands.show', $brand))
        ->assertOk()
        ->assertSee('<title>محصولات اوسرین', escape: false)
        ->assertSee('اوسرین')
        ->assertSee('آلمان');
});

it('finds a brand by its Persian slug', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_SLUG => 'سینره']);

    $this->get('/b/'.rawurlencode('سینره'))->assertOk()->assertSee('سینره');
});

it('does not show inactive, deleted or unknown brands', function (Closure $makeBrand): void {
    $makeBrand();

    $this->get('/b/eucerin')->assertNotFound();
})->with([
    'inactive' => [fn () => Brand::factory()->inactive()->create([Brand::COLUMN_SLUG => 'eucerin'])],
    'deleted' => [fn () => Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin'])->delete()],
    'unknown' => [fn (): null => null],
]);
