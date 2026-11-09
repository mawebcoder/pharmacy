<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The home page, built from the admin's widgets (roadmap B5, B9.2, S13b).
Route::livewire('/', 'content::home-page')->name('home');
