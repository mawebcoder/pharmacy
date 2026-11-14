<?php

declare(strict_types=1);

use App\Modules\Cart\Http\Controllers\CartQuantitiesController;
use Illuminate\Support\Facades\Route;

// The cart page (roadmap B5, B9.6, S19); guests and customers alike.
Route::livewire('/cart', 'cart::cart-page')->name('cart');

// How many of each SKU is in the shopper's cart, for the product cards (lightweight JSON, S19).
Route::get('/cart/quantities', CartQuantitiesController::class)->middleware('throttle:120,1')->name('cart.quantities');
