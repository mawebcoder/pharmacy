<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Actions\NotifyCustomer;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Notifications\Messages\CustomerMessage;

/**
 * Texts a customer about their order and keeps the same message in «اعلان‌ها» (owner, 2026-11-21), linked to the order's
 * page. The SMS is queued; the notification is kept even if the SMS later fails.
 */
final readonly class TextCustomer
{
    public function __construct(private NotifyCustomer $notify) {}

    public function __invoke(int $customerId, int $orderId, SmsMessageData $message): void
    {
        SendSmsJob::dispatch($message);

        $content = $message->template->customerMessage($message->args);

        if ($content !== null) {
            ($this->notify)($customerId, new CustomerMessage(
                $message->template->value,
                $content['title'],
                $content['body'],
                route('account.orders.show', $orderId),
            ));
        }
    }
}
