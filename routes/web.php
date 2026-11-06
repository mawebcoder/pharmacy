<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary: TallStackUI RTL / Persian evaluation page.
Route::livewire('/ui-test', 'ui-test')->name('ui-test');
