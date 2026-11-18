<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Checkout (roadmap B5, B9.7, B9.8, S23): the address step first, then review and pay, then the payment result.
// Customers only.
Route::middleware('auth:customer')->group(function (): void {
    Route::livewire('/checkout/address', 'checkout::address-step')->name('checkout.address');
    Route::livewire('/checkout', 'checkout::checkout-page')->name('checkout');
    Route::livewire('/checkout/result/{order}', 'checkout::payment-result')->whereNumber('order')->name('checkout.result');
});
