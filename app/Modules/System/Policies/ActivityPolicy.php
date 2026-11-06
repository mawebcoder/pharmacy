<?php

declare(strict_types=1);

namespace App\Modules\System\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone; super_admin passes these checks through Shield.
 */
final class ActivityPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Activity');
    }

    public function view(Authorizable $admin, Activity $activity): bool
    {
        return $admin->can('View:Activity');
    }
}
