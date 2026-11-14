<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\LogoutCustomerController;
use Illuminate\Support\Facades\Route;

// Customer sign-in with a one-time SMS code and sign-out (roadmap B9.10, D13.1, S17).
Route::livewire('/login', 'identity::login-page')->middleware('guest:customer')->name('login');
Route::post('/logout', LogoutCustomerController::class)->middleware('auth:customer')->name('logout');
