<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\OpenPaymentLink;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Exceptions\GatewayUnavailable;

/**
 * «پرداخت» on a pending order (roadmap S23, owner 2026-11-14): the order's open payment link is reused, or a new one
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

        return $this->open($order);
    }

    /**
     * The gateway address for an unpaid order, without asking who opens it: the texted payment link is signed for this
     * order (owner, 2026-11-19).
     *
     * @throws OrderRefused
     */
    #[\NoDiscard]
    public function open(Order $order): string
    {
        // A paid order staff edited to a higher total: the difference, within its hour (owner, 2026-11-19).
        if ($order->canPayDifference()) {
            try {
                return ($this->openPaymentLink)(new PaymentRequestData(
                    orderId: $order->getId(),
                    amount: $order->getDifferenceDue(),
                    description: 'تفاوت مبلغ سفارش '.$order->getNumber(),
                    mobile: (string) ($order->getCustomerSnapshot()['mobile'] ?? ''),
                    expiresAt: $order->getDifferenceDueUntil() ?? now(),
                    kind: PaymentKind::Difference,
                ));
            } catch (GatewayUnavailable) {
                throw OrderRefused::paymentUnavailable();
            }
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
