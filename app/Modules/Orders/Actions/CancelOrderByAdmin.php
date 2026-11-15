<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The admin cancels an order while it is «پرداخت‌شده» or «در حال آماده‌سازی» (roadmap D3.5), with a reason kept in
 * the status log. The money is marked «در انتظار بازگشت وجه» for a super admin to refund (by hand until Zarinpal).
 */
final readonly class CancelOrderByAdmin
{
    public function __construct(private ChangeOrderStatus $changeStatus) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($order, $adminId, $reason): void {
            ($this->changeStatus)($order, OrderStatus::Cancelled, OrderActor::Admin, $adminId, trim($reason));

            if ($order->getPaymentStatus() === PaymentStatus::Paid) {
                $order->setPaymentStatus(PaymentStatus::RefundPending)->save();
            }
        });
    }
}
