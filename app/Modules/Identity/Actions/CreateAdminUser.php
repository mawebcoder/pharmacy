<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateAdminUser
{
    #[\NoDiscard]
    public function __invoke(AdminUserData $data): AdminUser
    {
        if ($data->password === null) {
            throw new InvalidArgumentException('A new admin user needs a password.');
        }

        return DB::transaction(function () use ($data): AdminUser {
            $admin = AdminUser::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'is_active' => $data->isActive,
            ]);

            $admin->syncRoles($data->roles);

            return $admin;
        });
    }
}
