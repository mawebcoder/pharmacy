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
 * The customer cancels their order while it is «پرداخت‌شده» or «در حال آماده‌سازی» (owner, 2026-11-16; once on its way,
 * nobody can).
 * The money is marked «در انتظار بازگشت وجه» for the admin to refund (by hand until Zarinpal, S26).
 */
final readonly class CancelOrderByCustomer
{
    public function __construct(private ChangeOrderStatus $changeStatus) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, int $customerId): void
    {
        if ($order->getCustomerId() !== $customerId) {
            throw OrderRefused::notYours();
        }

        DB::transaction(function () use ($order, $customerId): void {
            ($this->changeStatus)($order, OrderStatus::Cancelled, OrderActor::Customer, $customerId);
            $order->setPaymentStatus(PaymentStatus::RefundPending)->save();
        });
    }
}
