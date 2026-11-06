<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Exceptions\AdminUserChangeRefused;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Updates an admin account without ever locking staff out: nobody can deactivate themselves or drop their own
 * super_admin role, and the last active super admin always stays.
 */
final class UpdateAdminUser
{
    #[\NoDiscard]
    public function __invoke(AdminUser $admin, AdminUserData $data, AdminUser $actor): AdminUser
    {
        return DB::transaction(function () use ($admin, $data, $actor): AdminUser {
            $admin = AdminUser::query()->lockForUpdate()->findOrFail($admin->id);
            $staysSuperAdmin = in_array(AdminRole::SuperAdmin->value, $data->roles, true) && $data->isActive;

            if ($admin->is($actor) && ! $data->isActive) {
                throw AdminUserChangeRefused::cannotDeactivateSelf();
            }

            if ($admin->is($actor) && $admin->isSuperAdmin() && ! in_array(AdminRole::SuperAdmin->value, $data->roles, true)) {
                throw AdminUserChangeRefused::cannotRemoveOwnSuperAdminRole();
            }

            if ($admin->isSuperAdmin() && $admin->is_active && ! $staysSuperAdmin && ! $this->anotherActiveSuperAdminExists($admin)) {
                throw AdminUserChangeRefused::lastSuperAdmin();
            }

            $admin->fill([
                'name' => $data->name,
                'email' => $data->email,
                'is_active' => $data->isActive,
            ]);

            if ($data->password !== null) {
                $admin->password = $data->password;
            }

            $admin->save();
            $admin->syncRoles($data->roles);

            return $admin;
        });
    }

    private function anotherActiveSuperAdminExists(AdminUser $admin): bool
    {
        return AdminUser::role(AdminRole::SuperAdmin->value)
            ->where('is_active', true)
            ->whereKeyNot($admin->getKey())
            ->lockForUpdate()
            ->exists();
    }
}
