<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\AdminUser;

/**
 * Admin accounts are never deleted (deactivate instead). super_admin passes every check through Shield.
 */
final class AdminUserPolicy
{
    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:AdminUser');
    }

    public function view(AdminUser $admin, AdminUser $target): bool
    {
        return $admin->can('View:AdminUser');
    }

    public function create(AdminUser $admin): bool
    {
        return $admin->can('Create:AdminUser');
    }

    public function update(AdminUser $admin, AdminUser $target): bool
    {
        return $admin->can('Update:AdminUser');
    }

    public function resetTwoFactor(AdminUser $admin, AdminUser $target): bool
    {
        return $admin->can('Update:AdminUser');
    }
}
