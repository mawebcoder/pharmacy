<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// «پیگیری سفارش» without signing in: order number and mobile (roadmap S24).
Route::livewire('/track', 'orders::track-order')->name('orders.track');
