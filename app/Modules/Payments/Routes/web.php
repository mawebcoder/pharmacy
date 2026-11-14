<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

// Back from the gateway (roadmap D7, S25); the fake «bank» page for local and staging until Zarinpal (S26).
Route::get('/payment/callback', PaymentCallbackController::class)->middleware('throttle:30,1')->name('payments.callback');
Route::livewire('/payment/fake/{authority}', 'payments::fake-gateway')->where('authority', 'FAKE[A-Z0-9]+')->name('payments.fake');
