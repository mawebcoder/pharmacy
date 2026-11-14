<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Checkout (roadmap B5, B9.7, S25): the address step first, then review and pay. Customers only.
Route::middleware('auth:customer')->group(function (): void {
    Route::livewire('/checkout/address', 'checkout::address-step')->name('checkout.address');
    Route::livewire('/checkout', 'checkout::checkout-page')->name('checkout');
});
