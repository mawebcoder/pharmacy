<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Auth\Events\Login;

/**
 * Keeps admin_users.last_login_at current for staff audits.
 */
final class RecordAdminLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'admin' || ! $event->user instanceof AdminUser) {
            return;
        }

        $event->user->setLastLoginAt(now())->saveQuietly();
    }
}
