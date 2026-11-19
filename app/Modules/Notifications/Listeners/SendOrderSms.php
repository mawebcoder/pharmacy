<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Queries\OrderNotice;

/**
 * Order news by SMS (roadmap S37, owner 2026-11-19): «پرداخت سفارش» with the customer's name and the order number
 * when it is paid, «ارسال سفارش» with the name, order number and Tipax tracking code when it is on its way, and
 * «لغو سفارش» with the name and order number when it is cancelled.
 * Queued on «sms»; a failed SMS never touches the order.
 */
final readonly class SendOrderSms
{
    public function __construct(private OrderNotice $orders) {}

    public function handle(OrderPaid|OrderOnItsWay|OrderCancelled $event): void
    {
        $order = $this->orders->for($event->orderId);

        if ($order === null) {
            return;
        }

        $message = match (true) {
            $event instanceof OrderPaid => new SmsMessageData($order['mobile'], SmsTemplate::OrderPaid, [$order['name'], $order['number']]),
            $event instanceof OrderOnItsWay => new SmsMessageData($order['mobile'], SmsTemplate::OrderOnTheWay, [$order['name'], $order['number'], (string) $order['tracking_code']]),
            $event instanceof OrderCancelled => new SmsMessageData($order['mobile'], SmsTemplate::OrderCancelled, [$order['name'], $order['number']]),
        };

        SendSmsJob::dispatch($message);
    }
}
