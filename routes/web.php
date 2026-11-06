<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Placeholder until the real home page (S33).
Route::livewire('/', 'pages::home')->name('home');

// Design-system gallery for design QA (roadmap B13); local environment only.
if (app()->environment('local')) {
    Route::livewire('/dev/ui', 'pages::dev.ui')->name('dev.ui');
}
