<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the staff roles and grants each one the admin pages it needs (deny by default). Each roadmap step
 * adds its new permissions here. Re-running only adds permissions; ones granted later in the admin are kept.
 */
final class RolesSeeder extends Seeder
{
    /**
     * Shield permission keys per role. super_admin needs none: it passes every check.
     *
     * @var array<string, list<string>>
     */
    public const array PERMISSIONS = [
        'operator' => [
            // S05 Locations: switch delivery on or off per city.
            'ViewAny:City', 'View:City', 'Update:City',
            // S10 Catalog: look up products (stock comes from providers in S14).
            'ViewAny:Product', 'View:Product',
        ],
        'content_editor' => [
            // S07 Catalog: create and edit brands (deleting is left to super admins).
            'ViewAny:Brand', 'View:Brand', 'Create:Brand', 'Update:Brand',
            // S08 Catalog: create, edit and reorder categories.
            'ViewAny:Category', 'View:Category', 'Create:Category', 'Update:Category',
            // S10 Catalog: products with their variants, FAQs and links.
            'ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product',
        ],
    ];

    public function run(): void
    {
        foreach (AdminRole::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'admin');

            foreach (self::PERMISSIONS[$roleName->value] ?? [] as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
