<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\OpenPaymentLink;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;

/**
 * «پرداخت» on a pending order (roadmap S25, owner 2026-11-14): the order's open payment link is reused, or a new one
 * is opened when the last attempt failed. The order keeps the prices, coupon and address it was created with.
 * Returns where to send the customer.
 */
final readonly class PayOrder
{
    public function __construct(private OpenPaymentLink $openPaymentLink) {}

    /**
     * @throws OrderRefused
     */
    #[\NoDiscard]
    public function __invoke(Order $order, int $customerId): string
    {
        if ($order->getCustomerId() !== $customerId) {
            throw OrderRefused::notYours();
        }

        if (! $order->canBePaid()) {
            throw OrderRefused::cannotBePaid();
        }

        try {
            return ($this->openPaymentLink)(new PaymentRequestData(
                orderId: $order->getId(),
                amount: $order->getTotal(),
                description: 'سفارش '.$order->getNumber(),
                mobile: (string) ($order->getCustomerSnapshot()['mobile'] ?? ''),
                expiresAt: $order->getExpiresAt() ?? now(),
            ));
        } catch (GatewayUnavailable) {
            throw OrderRefused::paymentUnavailable();
        }
    }
}
