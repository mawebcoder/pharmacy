<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Queries\OrderNotice;

/**
 * Order news by SMS (roadmap S37, owner 2026-11-19): «پرداخت سفارش» with the customer's name and the order number
 * when it is paid, and «ارسال سفارش» with the name, order number and Tipax tracking code when it is on its way.
 * Queued on «sms»; a failed SMS never touches the order.
 */
final readonly class SendOrderSms
{
    public function __construct(private OrderNotice $orders) {}

    public function handle(OrderPaid|OrderOnItsWay $event): void
    {
        $order = $this->orders->for($event->orderId);

        if ($order === null) {
            return;
        }

        $message = $event instanceof OrderPaid
            ? new SmsMessageData($order['mobile'], SmsTemplate::OrderPaid, [$order['name'], $order['number']])
            : new SmsMessageData($order['mobile'], SmsTemplate::OrderOnTheWay, [$order['name'], $order['number'], (string) $order['tracking_code']]);

        SendSmsJob::dispatch($message);
    }
}
