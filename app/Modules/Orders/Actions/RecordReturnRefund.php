<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderReturns;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت بازگشت وجه» on a return (roadmap S27, owner 2026-11-19): records that the return's refund was paid, with the
 * payment reference; only once Tipax picked the items up (items never sent: once approved). When it settles a cancelled
 * order waiting for its refund (every unit back and refunded), the order is marked refunded too.
 */
final readonly class RecordReturnRefund
{
    public function __construct(private OrderReturns $returns) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(OrderReturn $return, string $reference, ?int $adminId): void
    {
        DB::transaction(function () use ($return, $reference, $adminId): void {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->getId());

            if (! $return->isRefundable()) {
                throw OrderRefused::returnNotRefundable();
            }

            $return->setStatus(OrderReturnStatus::Refunded)
                ->setRefundedAt(now())
                ->setRefundedBy($adminId)
                ->setRefundReference(trim($reference))
                ->save();

            $this->settleOrder($return->getOrder(), trim($reference));
        });
    }

    /**
     * A cancelled order waiting for its refund is refunded once its open returns hold every unit and all are refunded.
     */
    private function settleOrder(Order $order, string $reference): void
    {
        if ($order->getPaymentStatus() !== PaymentStatus::RefundPending || $this->returns->scope($order) === null) {
            return;
        }

        $open = $order->returns()->where(OrderReturn::COLUMN_STATUS, '!=', OrderReturnStatus::Cancelled)->get();

        if (! OrderReturns::coversAll($order, $this->returns->returned($order->getId()))
            || $open->contains(fn (OrderReturn $return): bool => $return->getStatus() !== OrderReturnStatus::Refunded)) {
            return;
        }

        $order->setPaymentStatus(PaymentStatus::Refunded)
            ->setRefundedAt(now())
            ->setRefundReference($reference)
            ->setRefundItemsAmount((int) $open->sum(fn (OrderReturn $return): int => $return->getItemsRefund()))
            ->setRefundShippingAmount((int) $open->sum(fn (OrderReturn $return): int => $return->getShippingFeeRefund()))
            ->save();
    }
}
