<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Database\Seeder;
use RuntimeException;

final class SuperAdminSeeder extends Seeder
{
    /**
     * Create the first admin from config('auth.superadmin') with the super_admin role, or make sure the existing one
     * is active and still a super admin.
     * An existing password is never overwritten. The admin sets up 2FA on first sign-in.
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $superAdmin */
        $superAdmin = config('auth.superadmin');

        $admin = AdminUser::query()->where(AdminUser::COLUMN_EMAIL, $superAdmin['email'])->first()
            ?? (new AdminUser)->setEmail($superAdmin['email']);

        if (! $admin->exists) {
            if (app()->isProduction() && $superAdmin['password'] === 'password') {
                throw new RuntimeException('Set SUPERADMIN_PASSWORD in .env before seeding the super admin in production.');
            }

            $admin->setPassword($superAdmin['password']);
        }

        $admin->setName($superAdmin['name'])->setIsActive(true)->save();

        $this->call(RolesSeeder::class);
        $admin->assignRole(AdminRole::SuperAdmin->value);
    }
}
