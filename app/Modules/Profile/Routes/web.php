<?php

declare(strict_types=1);

use App\Modules\Profile\Http\Controllers\OrderInvoiceController;
use Illuminate\Support\Facades\Route;

// The customer's account area (roadmap B5, B9.9; the Profile module since 2026-11-18). Customers only; route names
// stay «account.*».
Route::middleware('auth:customer')->prefix('account')->group(function (): void {
    // «حساب من» opens the orders (owner, 2026-11-19: no «پیشخوان»).
    Route::redirect('/', '/account/orders')->name('account');
    Route::livewire('/orders', 'profile::order-list')->name('account.orders');
    Route::livewire('/orders/{order}', 'profile::order-detail')->whereNumber('order')->name('account.orders.show');
    Route::get('/orders/{order}/invoice', OrderInvoiceController::class)->whereNumber('order')->name('account.orders.invoice');
    Route::livewire('/addresses', 'profile::addresses')->name('account.addresses');
    Route::livewire('/wishlist', 'profile::wishlist')->name('account.wishlist');
    Route::livewire('/reviews', 'profile::reviews')->name('account.reviews');
    Route::livewire('/notifications', 'profile::notifications')->name('account.notifications');
    Route::livewire('/profile', 'profile::account-details')->name('account.profile');
});
