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
     * Shield permission keys per role. super_admin needs none: it passes every check. Suppliers and their prices (S15,
     * our costs) are left to super_admin.
     *
     * @var array<string, list<string>>
     */
    public const array PERMISSIONS = [
        'operator' => [
            // S05 Locations: switch delivery on or off per city.
            'ViewAny:City', 'View:City', 'Update:City',
            // S10 Catalog: look up products (stock comes from providers in S15).
            'ViewAny:Product', 'View:Product',
            // S22 Cart: look up coupons (creating and editing them is left to super admins).
            'ViewAny:Coupon', 'View:Coupon',
            // S19 Identity: look up customers and block or unblock them.
            'ViewAny:Customer', 'View:Customer', 'Update:Customer',
            // S24 Orders: look up orders and move their status, cancelling included (refunds are left to super admins).
            'ViewAny:Order', 'View:Order', 'Update:Order',
            // S25 Orders: open an order's purchase document, type its rows and record what arrived.
            'ViewAny:OrderPurchase', 'View:OrderPurchase', 'Create:OrderPurchase', 'Update:OrderPurchase',
            // S29 Content: read the contact messages and mark them answered.
            'ViewAny:ContactMessage', 'View:ContactMessage', 'Update:ContactMessage',
        ],
        'content_editor' => [
            // S07 Catalog: create and edit brands (deleting is left to super admins).
            'ViewAny:Brand', 'View:Brand', 'Create:Brand', 'Update:Brand',
            // S08 Catalog: create, edit and reorder categories.
            'ViewAny:Category', 'View:Category', 'Create:Category', 'Update:Category',
            // S10 Catalog: products with their variants, FAQs and links.
            'ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product',
            // S11 Catalog: attributes (with values) and tags.
            'ViewAny:Attribute', 'View:Attribute', 'Create:Attribute', 'Update:Attribute',
            'ViewAny:Tag', 'View:Tag', 'Create:Tag', 'Update:Tag',
            // S14 Catalog: collections, including «محصولات محبوب».
            'ViewAny:ProductCollection', 'View:ProductCollection', 'Create:ProductCollection', 'Update:ProductCollection',
            // S12 Search: synonyms and the search insights page.
            'ViewAny:SearchSynonym', 'View:SearchSynonym', 'Create:SearchSynonym', 'Update:SearchSynonym', 'Delete:SearchSynonym',
            'View:SearchInsights',
            // S14 Content: home page widgets and the banner library.
            'ViewAny:HomeWidget', 'View:HomeWidget', 'Create:HomeWidget', 'Update:HomeWidget', 'Delete:HomeWidget',
            'ViewAny:Banner', 'View:Banner', 'Create:Banner', 'Update:Banner', 'Delete:Banner',
            // S29 Content: static pages («صفحه‌های ثابت»).
            'ViewAny:Page', 'View:Page', 'Create:Page', 'Update:Page', 'Delete:Page', 'Restore:Page',
            // S29 Content: questions on /faq, the site menus, and the contact messages.
            'ViewAny:Faq', 'View:Faq', 'Create:Faq', 'Update:Faq', 'Delete:Faq',
            'ViewAny:Menu', 'View:Menu', 'Update:Menu',
            'ViewAny:ContactMessage', 'View:ContactMessage', 'Update:ContactMessage',
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
