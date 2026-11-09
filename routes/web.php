<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The home page lives in the Content module (S13b).

// Design-system gallery for design QA (roadmap B13); local environment only.
if (app()->environment('local')) {
    Route::livewire('/dev/ui', 'pages::dev.ui')->name('dev.ui');
}
