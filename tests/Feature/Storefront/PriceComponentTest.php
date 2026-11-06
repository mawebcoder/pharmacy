<?php

declare(strict_types=1);

it('shows a regular price in toman', function (): void {
    $this->blade('<x-ui.price :price="48_500_000" />')
        ->assertSee('۴٬۸۵۰٬۰۰۰ تومان')
        ->assertDontSee('<del', false);
});

it('shows the old price struck through with the discount percent', function (): void {
    $this->blade('<x-ui.price :price="12_500_000" :old="15_000_000" />')
        ->assertSeeInOrder(['<del', '۱٬۵۰۰٬۰۰۰ تومان', '</del>', '۱۷٪', '۱٬۲۵۰٬۰۰۰ تومان'], false);
});

it('does not show a discount when the old price is not higher', function (): void {
    $this->blade('<x-ui.price :price="12_500_000" :old="12_500_000" />')
        ->assertDontSee('<del', false)
        ->assertDontSee('٪');
});

it('shows a free price as free', function (): void {
    $this->blade('<x-ui.price :price="0" />')
        ->assertSee('رایگان')
        ->assertDontSee('تومان');
});
