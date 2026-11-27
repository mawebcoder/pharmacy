<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;

/**
 * Keeps a notification for every active super admin, under the admin panel's bell (S37, e.g. low SMS credit).
 */
final class NotifySuperAdmins
{
    public function __invoke(Notification $notification): void
    {
        AdminUser::query()
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', AdminRole::SuperAdmin->value))
            ->where(AdminUser::COLUMN_IS_ACTIVE, true)
            ->each(fn (AdminUser $admin) => $admin->notify($notification));
    }
}
