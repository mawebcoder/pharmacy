<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Subcategory;

use function Pest\Livewire\livewire;

function productForSaleIn(Subcategory $subcategory): Product
{
    $product = Product::factory()->active()->for($subcategory, 'primarySubcategory')->create();
    ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null, ProductVariant::COLUMN_PRICE => 1_000_000]);

    return $product;
}

it('shows the category page with a box for each subcategory that has a product for sale', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SLUG => 'supplements']);
    productForSaleIn(Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها', Subcategory::COLUMN_SLUG => 'vitamins']));
    Product::factory()->for(Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'زیردسته بدون فروش']), 'primarySubcategory')->create();
    productForSaleIn(Subcategory::factory()->of($supplements)->inactive()->create([Subcategory::COLUMN_NAME_FA => 'زیردسته غیرفعال']));

    $this->get('/categories/supplements')->assertOk();

    // The page component alone: the header menu lists every active subcategory.
    livewire('catalog::category-page', ['slug' => 'supplements'])
        ->assertSeeInOrder(['خانه', 'مکمل‌ها', 'همه', 'ویتامین‌ها'])
        ->assertSeeHtml(route('subcategory.show', ['supplements', 'vitamins']))
        ->assertDontSee('زیردسته بدون فروش')
        ->assertDontSee('زیردسته غیرفعال');
});

it('shows a subcategory page under its category', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SLUG => 'supplements']);
    Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها', Subcategory::COLUMN_SLUG => 'vitamins']);

    $this->get('/categories/supplements/vitamins')
        ->assertOk()
        ->assertSeeInOrder(['خانه', 'مکمل‌ها', 'ویتامین‌ها']);
});

it('does not show hidden or unknown categories', function (Closure $makeCategory): void {
    $makeCategory();

    $this->get('/categories/vitamins')->assertNotFound();
})->with([
    'inactive' => [fn () => Category::factory()->inactive()->create([Category::COLUMN_SLUG => 'vitamins'])],
    'deleted' => [fn () => Category::factory()->create([Category::COLUMN_SLUG => 'vitamins'])->delete()],
    'unknown' => [fn (): null => null],
]);

it('does not show hidden, misplaced or unknown subcategories', function (Closure $makeSubcategory): void {
    $makeSubcategory(Category::factory()->create([Category::COLUMN_SLUG => 'supplements']));

    $this->get('/categories/supplements/vitamins')->assertNotFound();
})->with([
    'inactive' => [fn (Category $category) => Subcategory::factory()->of($category)->inactive()->create([Subcategory::COLUMN_SLUG => 'vitamins'])],
    'under an inactive category' => [function (Category $category): void {
        Subcategory::factory()->of($category)->create([Subcategory::COLUMN_SLUG => 'vitamins']);
        $category->setIsActive(false)->save();
    }],
    'in another category' => [fn () => Subcategory::factory()->create([Subcategory::COLUMN_SLUG => 'vitamins'])],
    'deleted' => [fn (Category $category) => Subcategory::factory()->of($category)->create([Subcategory::COLUMN_SLUG => 'vitamins'])->delete()],
    'unknown' => [fn (): null => null],
]);

it('lists only the categories as tiles for the mobile tab', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SLUG => 'supplements']);
    Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Category::factory()->inactive()->create([Category::COLUMN_NAME_FA => 'دسته غیرفعال']);

    $this->get(route('categories.index'))->assertOk();
    livewire('catalog::category-index')
        ->assertSee('مکمل‌ها')
        ->assertSeeHtml(route('category.show', 'supplements'))
        ->assertDontSee('ویتامین‌ها')
        ->assertDontSee('دسته غیرفعال');
});

it('puts the category menu in the header of every storefront page, with only subcategories that have a product for sale', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SLUG => 'supplements']);
    productForSaleIn(Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها', Subcategory::COLUMN_SLUG => 'vitamins']));
    Subcategory::factory()->of($supplements)->create([Subcategory::COLUMN_NAME_FA => 'زیردسته خالی', Subcategory::COLUMN_SLUG => 'empty']);
    $beauty = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرایشی', Category::COLUMN_SLUG => 'beauty']);
    Subcategory::factory()->of($beauty)->create();

    $menu = $this->get(route('home'))->assertOk()->assertSee('aria-label="دسته‌بندی محصولات"', escape: false)->getContent();
    $panel = str($menu)->after('id="mega-menu"')->toString();

    expect($panel)
        ->toContain(route('category.show', 'supplements'))
        ->toContain(route('subcategory.show', ['supplements', 'vitamins']))
        ->not->toContain(route('subcategory.show', ['supplements', 'empty']))
        ->not->toContain(route('category.show', 'beauty'));
});

it('shows the category description with its rich-editor formatting and without scripts', function (): void {
    Category::factory()->create([
        Category::COLUMN_SLUG => 'supplements',
        Category::COLUMN_DESCRIPTION => '<p><strong>مکمل‌های معتبر</strong></p><ol><li>ویتامین‌ها</li></ol><script>alert(1)</script>',
    ]);

    livewire('catalog::category-page', ['slug' => 'supplements'])
        ->assertSeeHtml('<strong>مکمل‌های معتبر</strong>')
        ->assertSeeHtml('<ol><li>ویتامین‌ها</li></ol>')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});
