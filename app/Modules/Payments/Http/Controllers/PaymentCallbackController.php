<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Actions\HandlePaymentCallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the gateway sends the customer back (roadmap D7.5): public, reads only the query string; every decision comes
 * from verifying with the gateway. Ends on the order's payment result page.
 */
final class PaymentCallbackController
{
    public function __invoke(Request $request, HandlePaymentCallback $handleCallback): RedirectResponse
    {
        $orderId = $handleCallback($request->query());

        return $orderId === null ? redirect()->route('home') : redirect()->route('checkout.result', $orderId);
    }
}
