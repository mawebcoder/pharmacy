<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\AdminOrderInvoiceController;
use App\Modules\Orders\Http\Controllers\PayOrderLinkController;
use Illuminate\Support\Facades\Route;

// «پیگیری سفارش» without signing in: order number and mobile (roadmap S24).
Route::livewire('/track', 'orders::track-order')->name('orders.track');

// The printable invoice behind «چاپ فاکتور» on the admin order page (owner, 2026-11-19).
Route::get('/admin/print/orders/{order}/invoice', AdminOrderInvoiceController::class)
    ->middleware('auth:admin')
    ->whereNumber('order')
    ->name('orders.invoice.print');

// The payment link texted for an unpaid order (owner, 2026-11-19): signed, valid until the order expires.
Route::get('/pay/{order}', PayOrderLinkController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('order')
    ->name('orders.pay');
