<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Notifications\Notification;

/**
 * Keeps a notification for one admin, under the admin panel's bell (e.g. a catalog import's result); nothing when
 * the admin is gone.
 */
final class NotifyAdmin
{
    public function __invoke(int $adminId, Notification $notification): void
    {
        AdminUser::query()->find($adminId)?->notify($notification);
    }
}
