<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Events\OrderReturnRequested;
use App\Modules\Orders\Queries\OrderNotice;
use App\Modules\Settings\Queries\StoreSettings;

/**
 * A customer asked for a return from their account (roadmap S27, owner 2026-11-19): every staff mobile in «تنظیمات
 * فروشگاه» › «اعلان مرجوعی» gets «درخواست مرجوعی» with the customer's name, the order number and the return number.
 * Nobody is texted while no mobile is set. Queued on «sms».
 */
final readonly class SendReturnRequestSms
{
    public function __construct(
        private OrderNotice $orders,
        private StoreSettings $settings,
    ) {}

    public function handle(OrderReturnRequested $event): void
    {
        $order = $this->orders->for($event->orderId);

        if ($order === null) {
            return;
        }

        foreach ($this->settings->returnNotifyMobiles() as $mobile) {
            SendSmsJob::dispatch(new SmsMessageData($mobile, SmsTemplate::ReturnRequested, [$order['name'], $order['number'], $event->returnNumber]));
        }
    }
}
