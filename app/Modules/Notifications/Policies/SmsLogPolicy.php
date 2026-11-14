<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Policies;

use App\Modules\Notifications\Models\SmsLog;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * SMS logs are read-only for everyone; super_admin passes these checks through Shield.
 */
final class SmsLogPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:SmsLog');
    }

    public function view(Authorizable $admin, SmsLog $log): bool
    {
        return $admin->can('View:SmsLog');
    }
}
