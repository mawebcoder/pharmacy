<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\PayOrder;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * The texted payment link (owner, 2026-11-19): signed for one order until it expires; sends the customer to the
 * gateway, or to their order page when it can no longer be paid.
 */
final class PayOrderLinkController
{
    public function __invoke(Order $order, PayOrder $pay): RedirectResponse
    {
        try {
            return redirect()->away($pay->open($order));
        } catch (OrderRefused $refused) {
            return redirect()->route('account.orders.show', $order->getId())->with('status', $refused->getMessage());
        }
    }
}
