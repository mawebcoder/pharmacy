<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Customer;
use Illuminate\Notifications\Notification;

/**
 * Keeps a notification for a customer (owner, 2026-11-21: «اعلان‌ها», one per SMS sent to them); nothing when the
 * customer is gone.
 */
final class NotifyCustomer
{
    public function __invoke(int $customerId, Notification $notification): void
    {
        Customer::query()->find($customerId)?->notify($notification);
    }
}
