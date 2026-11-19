<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * «تحویل به حمل»: the admin hands a prepared order to the carrier and types its tracking code (roadmap S24; Tipax
 * sets both itself from S35). The customer sees the code on their order page and on «پیگیری سفارش». Only once the
 * order's purchase document has fully arrived («آماده بسته‌بندی», S25, owner 2026-11-16). OrderOnItsWay goes out after
 * the commit (the customer's SMS, S37).
 */
final readonly class SendOrderOnItsWay
{
    public function __construct(private ChangeOrderStatus $changeStatus) {}

    /**
     * @throws OrderRefused
     */
    public function __invoke(Order $order, string $trackingCode, int $adminId): void
    {
        if ($order->getSourcingStatus() !== SourcingStatus::ReadyToPack) {
            throw OrderRefused::notSourced();
        }

        DB::transaction(function () use ($order, $trackingCode, $adminId): void {
            $order->setTrackingCode(trim($trackingCode));
            ($this->changeStatus)($order, OrderStatus::OnTheWay, OrderActor::Admin, $adminId);
            OrderOnItsWay::dispatch($order->getId());
        });
    }
}
