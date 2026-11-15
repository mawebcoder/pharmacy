<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت بازگشت وجه»: a super admin records that a cancelled order's money was paid back by hand (roadmap S29, until
 * Zarinpal refunds), with the bank or gateway panel reference. Only an order «در انتظار بازگشت وجه»; the change is in
 * the order's audit log.
 */
final readonly class MarkOrderRefunded
{
    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, string $reference): void
    {
        DB::transaction(function () use ($order, $reference): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getId());

            if ($order->getPaymentStatus() !== PaymentStatus::RefundPending) {
                throw OrderRefused::notRefundable();
            }

            $order->setPaymentStatus(PaymentStatus::Refunded)
                ->setRefundedAt(now())
                ->setRefundReference(trim($reference))
                ->save();
        });
    }
}
