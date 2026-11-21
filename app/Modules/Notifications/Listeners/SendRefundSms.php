<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Payments\Events\RefundApproved;
use App\Modules\Shared\Support\Toman;

/**
 * A refund approved by staff (owner, 2026-11-19): «بازگشت وجه» to the customer with their name, the order number and
 * the amount. Queued on «sms».
 */
final readonly class SendRefundSms
{
    public function __construct(private OrderNotice $orders) {}

    public function handle(RefundApproved $event): void
    {
        $order = $this->orders->for($event->orderId);

        if ($order !== null) {
            SendSmsJob::dispatch(new SmsMessageData($order['mobile'], SmsTemplate::RefundPaid, [$order['name'], $order['number'], Toman::format($event->amount)]));
        }
    }
}
