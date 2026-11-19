<?php

declare(strict_types=1);

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\OrderReturn;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Order returns in the admin (roadmap S27). super_admin passes every check through Shield; operators open returns,
 * approve them (registering the pickup with Tipax) and cancel them. Returns are never deleted.
 */
final class OrderReturnPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:OrderReturn');
    }

    public function view(Authorizable $admin, OrderReturn $return): bool
    {
        return $admin->can('View:OrderReturn');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:OrderReturn');
    }

    /**
     * Approving and cancelling.
     */
    public function update(Authorizable $admin, OrderReturn $return): bool
    {
        return $admin->can('Update:OrderReturn');
    }
}
