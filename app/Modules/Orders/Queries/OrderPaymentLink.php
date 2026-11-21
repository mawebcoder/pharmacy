<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * The payment link texted to the customer for an unpaid order (owner, 2026-11-19): a signed address on our site, valid
 * until the order expires, that sends the customer to the gateway without signing in.
 */
final readonly class OrderPaymentLink
{
    public function for(Order $order): ?string
    {
        // The difference owed after an edit, for its hour (owner, 2026-11-19).
        if ($order->canPayDifference()) {
            return URL::temporarySignedRoute('orders.pay', $order->getDifferenceDueUntil() ?? now(), ['order' => $order->getId()]);
        }

        if (! $order->canBePaid() || $order->getExpiresAt() === null) {
            return null;
        }

        return URL::temporarySignedRoute('orders.pay', $order->getExpiresAt(), ['order' => $order->getId()]);
    }

    public function forOrder(int $orderId): ?string
    {
        $order = Order::query()->find($orderId);

        return $order instanceof Order ? $this->for($order) : null;
    }
}
