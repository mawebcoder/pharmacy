<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Orders\Queries\OrderPaymentLink;

/**
 * An unpaid order's payment link to the customer (owner, 2026-11-19): «لینک پرداخت سفارش» with the customer's name, the
 * order number and the signed link, when staff place the order in the panel or send the link again. Nothing is sent
 * once the order can no longer be paid. Queued on «sms».
 */
final readonly class SendPaymentLinkSms
{
    public function __construct(
        private OrderNotice $orders,
        private OrderPaymentLink $links,
    ) {}

    public function handle(OrderPaymentLinkRequested $event): void
    {
        $order = $this->orders->for($event->orderId);
        $link = $this->links->forOrder($event->orderId);

        if ($order === null || $link === null) {
            return;
        }

        SendSmsJob::dispatch(new SmsMessageData($order['mobile'], SmsTemplate::OrderPaymentLink, [$order['name'], $order['number'], $link]));
    }
}
