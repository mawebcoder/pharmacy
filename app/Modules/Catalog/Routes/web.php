<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Brand index and brand page (roadmap B5). Full words in addresses (decided 2026-11-07 in S13b): /brands, /categories, /products.
Route::livewire('/brands', 'catalog::brand-index')->name('brands.index');
Route::livewire('/brands/{slug}', 'catalog::brand-page')->name('brands.show');

// Category list for the mobile "دسته‌ها" tab (roadmap B9.1) and the category page (B5, PLP shell until S12).
Route::livewire('/categories', 'catalog::category-index')->name('categories.index');
Route::livewire('/categories/{slug}', 'catalog::category-page')->name('category.show');

// Tag page: nutrient / need chips link here (roadmap B5); the product list comes in S12.
Route::livewire('/t/{slug}', 'catalog::tag-page')->name('tags.show');

// Collections: the list and a collection's products (roadmap S13b); /collections/popular is «محصولات محبوب».
Route::livewire('/collections', 'catalog::collection-index')->name('collections.index');
Route::livewire('/collections/{slug}', 'catalog::collection-page')->name('collections.show');

// Product page: simple until S13 builds the full page (roadmap B5).
Route::livewire('/products/{slug}', 'catalog::product-page')->name('products.show');

// The customer's saved products, inside the account area (roadmap S18).
Route::livewire('/account/wishlist', 'catalog::account.wishlist')->middleware('auth:customer')->name('account.wishlist');

// The first short addresses (/b, /c, /p) keep working: permanent redirects to the new ones.
Route::permanentRedirect('/b', '/brands');
Route::permanentRedirect('/b/{slug}', '/brands/{slug}');
Route::permanentRedirect('/c/{slug}', '/categories/{slug}');
Route::permanentRedirect('/p/{slug}', '/products/{slug}');
