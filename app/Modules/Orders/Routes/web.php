<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// «پیگیری سفارش» without signing in: order number and mobile (roadmap S24).
Route::livewire('/track', 'orders::track-order')->name('orders.track');

// The payment result and the customer's orders (roadmap B5, B9.8, B9.9, S23). Customers only.
Route::middleware('auth:customer')->group(function (): void {
    Route::livewire('/checkout/result/{order}', 'orders::payment-result')->whereNumber('order')->name('checkout.result');
    Route::livewire('/account/orders', 'orders::account.order-list')->name('account.orders');
    Route::livewire('/account/orders/{order}', 'orders::account.order-detail')->whereNumber('order')->name('account.orders.show');
});
