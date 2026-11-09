<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;

it('shows the category page with breadcrumbs and active subcategories', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
    $vitamins = Category::factory()->childOf($supplements)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها', Category::COLUMN_SLUG => 'vitamins']);
    Category::factory()->childOf($vitamins)->create([Category::COLUMN_NAME_FA => 'ویتامین D']);
    Category::factory()->childOf($vitamins)->inactive()->create([Category::COLUMN_NAME_FA => 'ویتامین غیرفعال']);

    $this->get('/categories/vitamins')
        ->assertOk()
        ->assertSeeInOrder(['خانه', 'مکمل‌ها', 'ویتامین‌ها', 'ویتامین D'])
        ->assertDontSee('ویتامین غیرفعال');
});

it('does not show hidden or unknown categories', function (Closure $makeCategory): void {
    $makeCategory();

    $this->get('/categories/vitamins')->assertNotFound();
})->with([
    'inactive' => [fn () => Category::factory()->inactive()->create([Category::COLUMN_SLUG => 'vitamins'])],
    'under an inactive parent' => [fn () => Category::factory()->childOf(Category::factory()->inactive()->create())->create([Category::COLUMN_SLUG => 'vitamins'])],
    'deleted' => [fn () => Category::factory()->create([Category::COLUMN_SLUG => 'vitamins'])->delete()],
    'unknown' => [fn (): null => null],
]);

it('lists the categories two levels deep for the mobile tab', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
    Category::factory()->childOf($supplements)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Category::factory()->inactive()->create([Category::COLUMN_NAME_FA => 'دسته غیرفعال']);

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['مکمل‌ها', 'همه مکمل‌ها', 'ویتامین‌ها'])
        ->assertDontSee('دسته غیرفعال');
});

it('puts the category menu in the header of every storefront page', function (): void {
    $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها', Category::COLUMN_SLUG => 'supplements']);
    $vitamins = Category::factory()->childOf($supplements)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
    Category::factory()->childOf($vitamins)->create([Category::COLUMN_NAME_FA => 'ویتامین D', Category::COLUMN_SLUG => 'vitamin-d']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('aria-label="دسته‌بندی محصولات"', escape: false)
        ->assertSee(route('category.show', 'supplements'), escape: false)
        ->assertSee(route('category.show', 'vitamin-d'), escape: false);
});
