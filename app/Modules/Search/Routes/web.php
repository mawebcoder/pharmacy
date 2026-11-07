<?php

declare(strict_types=1);

use App\Modules\Search\Http\Controllers\OpenSearchResultController;
use Illuminate\Support\Facades\Route;

// Search page (roadmap B5 /search?q=) and opening a product from its results (click logging, D21.4.4).
Route::livewire('/search', 'search::search-page')->name('search');
Route::get('/search/open/{searchLog}/{product}/{slug}', OpenSearchResultController::class)
    ->whereNumber(['searchLog', 'product'])
    ->middleware('throttle:60,1')
    ->name('search.open');
