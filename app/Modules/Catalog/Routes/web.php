<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Brand index and brand page (roadmap B5). The brand page becomes a full product list in S12.
Route::livewire('/b', 'catalog::brand-index')->name('brands.index');
Route::livewire('/b/{slug}', 'catalog::brand-page')->name('brands.show');

// Category list for the mobile "دسته‌ها" tab (roadmap B9.1) and the category page (B5, PLP shell until S12).
Route::livewire('/categories', 'catalog::category-index')->name('categories.index');
Route::livewire('/c/{slug}', 'catalog::category-page')->name('category.show');

// Tag page: nutrient / need chips link here (roadmap B5); the product list comes in S12.
Route::livewire('/t/{slug}', 'catalog::tag-page')->name('tags.show');

// Product page: simple until S13 builds the full page (roadmap B5).
Route::livewire('/p/{slug}', 'catalog::product-page')->name('products.show');
