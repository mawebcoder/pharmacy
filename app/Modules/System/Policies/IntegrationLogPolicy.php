<?php

declare(strict_types=1);

namespace App\Modules\System\Policies;

use App\Modules\System\Models\IntegrationLog;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Integration logs are read-only for everyone; super_admin passes these checks through Shield.
 */
final class IntegrationLogPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:IntegrationLog');
    }

    public function view(Authorizable $admin, IntegrationLog $log): bool
    {
        return $admin->can('View:IntegrationLog');
    }
}
