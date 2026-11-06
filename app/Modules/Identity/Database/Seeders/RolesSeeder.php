<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the staff roles. operator and content_editor start without permissions (deny by default);
 * each roadmap step grants its new admin pages to the right role.
 */
final class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminRole::cases() as $role) {
            Role::findOrCreate($role->value, 'admin');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
