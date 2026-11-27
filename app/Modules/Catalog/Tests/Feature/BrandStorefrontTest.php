<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;

use function Pest\Livewire\livewire;

it('lists only active brands in one list, in Persian alphabet order, without letter headings', function (): void {
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_NAME_EN => 'Cinere']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'آردن', Brand::COLUMN_NAME_EN => 'Arden']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin']);
    Brand::factory()->inactive()->create([Brand::COLUMN_NAME_FA => 'برند غیرفعال']);
    Brand::factory()->create([Brand::COLUMN_NAME_FA => 'برند حذف‌شده'])->delete();

    $this->get(route('brands.index'))
        ->assertOk()
        ->assertSeeInOrder(['آردن', 'اوسرین', 'سینره'])
        ->assertDontSee('فهرست حروف')
        ->assertDontSee('برند غیرفعال')
        ->assertDontSee('برند حذف‌شده');
});

it('shows 20 brands and loads the next 20 as the shopper scrolls', function (): void {
    Brand::factory()->count(25)->create();

    livewire('catalog::brand-index')
        ->assertSet('shown', 20)
        ->assertSeeHtml('$wire.loadMore()')
        ->call('loadMore')
        ->assertSet('shown', 25)
        ->assertDontSeeHtml('$wire.loadMore()');
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

    $this->get('/brands/'.rawurlencode('سینره'))->assertOk()->assertSee('سینره');
});

it('does not show inactive, deleted or unknown brands', function (Closure $makeBrand): void {
    $makeBrand();

    $this->get('/brands/eucerin')->assertNotFound();
})->with([
    'inactive' => [fn () => Brand::factory()->inactive()->create([Brand::COLUMN_SLUG => 'eucerin'])],
    'deleted' => [fn () => Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin'])->delete()],
    'unknown' => [fn (): null => null],
]);

it('shows the brand description with its rich-editor formatting and without scripts', function (): void {
    $brand = Brand::factory()->create([
        Brand::COLUMN_SLUG => 'eucerin',
        Brand::COLUMN_DESCRIPTION => '<h2>درباره اوسرین</h2><p><em>از ۱۹۰۰</em></p><script>alert(1)</script>',
    ]);

    $this->get(route('brands.show', $brand))
        ->assertOk()
        ->assertSee(['<h2>درباره اوسرین</h2>', '<em>از ۱۹۰۰</em>'], false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
