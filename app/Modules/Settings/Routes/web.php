<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The pharmacist in charge and the store's pharmacists (roadmap B5, S30).
Route::livewire('/pharmacist', 'settings::pharmacist-page')->name('pharmacist');
