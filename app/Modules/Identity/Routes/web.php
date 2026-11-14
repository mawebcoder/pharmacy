<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\LogoutCustomerController;
use Illuminate\Support\Facades\Route;

// Customer sign-in with a one-time SMS code and sign-out (roadmap B9.10, D13.1, S17).
Route::livewire('/login', 'identity::login-page')->middleware('guest:customer')->name('login');
Route::post('/logout', LogoutCustomerController::class)->middleware('auth:customer')->name('logout');

// Account area (roadmap B5, B9.9, S18). «سفارش‌ها» is in the Orders module (S25); reviews show an empty state until S13.
Route::middleware('auth:customer')->prefix('account')->group(function (): void {
    Route::livewire('/', 'identity::account.dashboard')->name('account');
    Route::livewire('/addresses', 'identity::account.addresses')->name('account.addresses');
    Route::livewire('/profile', 'identity::account.profile')->name('account.profile');
    Route::livewire('/reviews', 'identity::account.reviews')->name('account.reviews');
});
