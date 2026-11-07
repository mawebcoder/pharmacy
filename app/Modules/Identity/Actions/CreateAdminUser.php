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
            $admin = (new AdminUser)
                ->setName($data->name)
                ->setEmail($data->email)
                ->setPassword($data->password)
                ->setIsActive($data->isActive);
            $admin->save();

            $admin->syncRoles($data->roles);

            return $admin;
        });
    }
}
