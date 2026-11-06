<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): View => view('welcome'));

// Temporary: TallStackUI RTL / Persian evaluation page.
Route::livewire('/ui-test', 'ui-test')->name('ui-test');
