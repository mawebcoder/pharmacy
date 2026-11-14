<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;

/**
 * Customers sign themselves up (S17); staff only look them up and block or unblock them. super_admin passes every
 * check through Shield.
 */
final class CustomerPolicy
{
    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:Customer');
    }

    public function view(AdminUser $admin, Customer $customer): bool
    {
        return $admin->can('View:Customer');
    }

    public function block(AdminUser $admin, Customer $customer): bool
    {
        return $admin->can('Update:Customer');
    }
}
