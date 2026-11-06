<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the super admin from config('auth.superadmin'), or make sure the existing account is still an admin.
     * An existing password is never overwritten.
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $superAdmin */
        $superAdmin = config('auth.superadmin');

        $user = User::firstOrNew(['email' => $superAdmin['email']]);

        if (! $user->exists) {
            if (app()->isProduction() && $superAdmin['password'] === 'password') {
                throw new RuntimeException('Set SUPERADMIN_PASSWORD in .env before seeding the super admin in production.');
            }

            $user->password = $superAdmin['password'];
            $user->email_verified_at = now();
        }

        $user->name = $superAdmin['name'];
        $user->forceFill(['is_admin' => true])->save();
    }
}
