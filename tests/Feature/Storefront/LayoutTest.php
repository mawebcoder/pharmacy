<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;

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
    // Guests see «ورود» in place of «حساب من» (S17).
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
