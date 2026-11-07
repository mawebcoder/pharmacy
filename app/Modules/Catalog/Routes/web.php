<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Brand index and brand page (roadmap B5). The brand page becomes a full product list in S12.
Route::livewire('/b', 'catalog::brand-index')->name('brands.index');
Route::livewire('/b/{slug}', 'catalog::brand-page')->name('brands.show');
