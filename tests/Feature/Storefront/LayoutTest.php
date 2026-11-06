<?php

declare(strict_types=1);

it('renders the home page right-to-left in Persian inside the storefront layout', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('<html lang="fa" dir="rtl">', false)
        ->assertSee('<title>خانه | داروخانه آنلاین</title>', false)
        ->assertSee('پرش به محتوای اصلی')
        ->assertSee('role="search"', false)
        ->assertSee('ضمانت بازگشت');
});

it('shows the five bottom navigation items and marks the current page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['خانه', 'دسته‌ها', 'جستجو', 'سبد خرید', 'حساب من'])
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
