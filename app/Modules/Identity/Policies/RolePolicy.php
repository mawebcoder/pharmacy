<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\AdminUser;
use Spatie\Permission\Models\Role;

/**
 * Shield's role management page. super_admin passes every check through Shield.
 */
final class RolePolicy
{
    public function viewAny(AdminUser $admin): bool
    {
        return $admin->can('ViewAny:Role');
    }

    public function view(AdminUser $admin, Role $role): bool
    {
        return $admin->can('View:Role');
    }

    public function create(AdminUser $admin): bool
    {
        return $admin->can('Create:Role');
    }

    public function update(AdminUser $admin, Role $role): bool
    {
        return $admin->can('Update:Role');
    }

    public function delete(AdminUser $admin, Role $role): bool
    {
        return $admin->can('Delete:Role');
    }
}
