<?php

declare(strict_types=1);

use App\Modules\Shipping\Http\Controllers\TipaxCallbackController;
use Illuminate\Support\Facades\Route;

// Tipax posts the final result of an order registration here (roadmap S35); the secret in the address is checked.
Route::post('/webhooks/tipax/order/{result}', TipaxCallbackController::class)
    ->whereIn('result', ['success', 'failure'])
    ->middleware('throttle:60,1')
    ->name('shipping.tipax.callback');
