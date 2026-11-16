<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Support\Toman;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت بازگشت وجه»: a super admin records that a cancelled order's money was paid back by hand (roadmap S29, until
 * Zarinpal refunds), with the bank or gateway panel reference. Only an order «در انتظار بازگشت وجه»; the change is in
 * the order's audit log. The admin enters what was paid back for the products and for shipping (owner, 2026-11-16),
 * each at most what the customer paid for it (products: subtotal − coupon; shipping: shipping total), together more
 * than zero.
 */
final readonly class MarkOrderRefunded
{
    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, string $reference, int $itemsAmount, int $shippingAmount): void
    {
        DB::transaction(function () use ($order, $reference, $itemsAmount, $shippingAmount): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getId());

            if ($order->getPaymentStatus() !== PaymentStatus::RefundPending) {
                throw OrderRefused::notRefundable();
            }

            if ($itemsAmount > $order->getItemsPaidTotal()) {
                throw OrderRefused::refundTooLarge('کالاها', Toman::format($order->getItemsPaidTotal()));
            }

            if ($shippingAmount > $order->getShippingTotal()) {
                throw OrderRefused::refundTooLarge('هزینه ارسال', Toman::format($order->getShippingTotal()));
            }

            if ($itemsAmount < 0 || $shippingAmount < 0 || $itemsAmount + $shippingAmount === 0) {
                throw OrderRefused::emptyRefund();
            }

            $order->setPaymentStatus(PaymentStatus::Refunded)
                ->setRefundedAt(now())
                ->setRefundReference(trim($reference))
                ->setRefundItemsAmount($itemsAmount)
                ->setRefundShippingAmount($shippingAmount)
                ->save();
        });
    }
}
