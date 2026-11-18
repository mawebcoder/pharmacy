<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Content\Database\Seeders\DraftPagesSeeder;
use App\Modules\Content\Database\Seeders\MenusSeeder;
use App\Modules\Content\Models\Page;
use App\Modules\Identity\Models\Customer;

beforeEach(function (): void {
    // Starting data the migrations no longer insert (owner, 2026-11-17).
    $this->seed(DraftPagesSeeder::class);
    $this->seed(MenusSeeder::class);
});

it('renders the home page right-to-left in Persian inside the storefront layout', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<html lang="fa" dir="rtl"', false)
        ->assertSee('<title>خانه | داروخانه آنلاین</title>', false)
        ->assertSee('پرش به محتوای اصلی')
        ->assertSee('role="search"', false)
        ->assertSee('ضمانت بازگشت');
});

it('shows the five bottom navigation items and marks the current page', function (): void {
    // Guests see «ورود» in place of «حساب من» (S19).
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['خانه', 'دسته‌ها', 'جستجو', 'سبد خرید', 'ورود'])
        ->assertSee('aria-current="page"', false);
});

it('gives the desktop and mobile search fields their own labels', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('for="site-search"', false)
        ->assertSee('for="site-search-mobile"', false);
});

it('does not serve the design-system gallery outside the local environment', function (): void {
    $this->get('/dev/ui')->assertNotFound();
});

it('lists the account pages, orders included, in the signed-in header menu', function (): void {
    $this->actingAs(Customer::factory()->create(), 'customer')
        ->get('/')
        ->assertOk()
        ->assertSee('href="'.route('account.orders').'" wire:navigate role="menuitem"', false)
        ->assertSeeInOrder(['حساب من', 'سفارش‌ها', 'آدرس‌ها', 'علاقه‌مندی‌ها', 'اطلاعات حساب', 'خروج']);
});

it('hides the footer on phones on the cart and the checkout steps only', function (): void {
    $phonesOnly = '<div class="hidden md:block">';

    $this->get('/')->assertOk()->assertSee('<footer', false)->assertDontSee($phonesOnly, false);
    $this->get('/cart')->assertOk()->assertSee('<footer', false)->assertSee($phonesOnly, false);
});

it('puts «همه کالاها» and «برندها» in the category bar, and «درباره ما» only once that page is live', function (): void {
    Category::factory()->create();
    $about = Page::query()->where(Page::COLUMN_SLUG, 'about')->sole();

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['همه کالاها', 'برندها'])
        ->assertSee(route('brands.index'), escape: false)
        ->assertDontSee($about->url(), escape: false);

    $about->setIsActive(true)->save();

    $this->get('/')->assertOk()->assertSeeInOrder(['برندها', 'درباره ما'])->assertSee($about->url(), escape: false);
});
