<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\EditSubcategory;
use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\ListSubcategories;
use App\Modules\Catalog\Filament\Resources\Subcategories\Pages\ViewSubcategory;
use App\Modules\Catalog\Filament\Resources\Subcategories\RelationManagers\SubcategoryProductsRelationManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

describe('as a super admin', function (): void {
    beforeEach(function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    });

    it('lists subcategories with their category and the number of products they list', function (): void {
        $category = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
        $vitamins = Subcategory::factory()->of($category)->create();
        $empty = Subcategory::factory()->of($category)->create();
        Product::factory()->for($vitamins, 'primarySubcategory')->count(2)->create();
        Product::factory()->create()->subcategories()->attach($vitamins);
        Product::factory()->for($vitamins, 'primarySubcategory')->create()->delete();

        livewire(ListSubcategories::class)
            ->assertCanSeeTableRecords([$vitamins, $empty])
            ->assertTableColumnStateSet('category.name_fa', 'مکمل‌ها', $vitamins)
            ->assertTableColumnStateSet('products_count', 3, $vitamins)
            ->assertTableColumnStateSet('products_count', 0, $empty);
    });

    it('moves a subcategory to another category from its edit page', function (): void {
        $subcategory = Subcategory::factory()->create();
        $product = Product::factory()->for($subcategory, 'primarySubcategory')->create();
        $target = Category::factory()->create();

        livewire(EditSubcategory::class, ['record' => $subcategory->getRouteKey()])
            ->fillForm([Subcategory::COLUMN_CATEGORY_ID => $target->getId()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($subcategory->fresh()->getCategoryId())->toBe($target->getId())
            ->and($product->fresh()->getPrimaryCategoryId())->toBe($target->getId());
    });

    it('lists the products of a subcategory, as main or extra subcategory, on its view page', function (): void {
        $subcategory = Subcategory::factory()->create();
        $main = Product::factory()->for($subcategory, 'primarySubcategory')->create();
        $extra = Product::factory()->create();
        $extra->subcategories()->attach($subcategory);
        $other = Product::factory()->create();

        $this->get('/admin/subcategories/'.$subcategory->getRouteKey())->assertOk();

        livewire(SubcategoryProductsRelationManager::class, ['ownerRecord' => $subcategory, 'pageClass' => ViewSubcategory::class])
            ->assertCanSeeTableRecords([$main, $extra])
            ->assertCanNotSeeTableRecords([$other]);
    });
});

it('lets a content editor list, view and edit subcategories', function (): void {
    $subcategory = Subcategory::factory()->create();
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');

    $this->get('/admin/subcategories')->assertOk();
    $this->get('/admin/subcategories/'.$subcategory->getRouteKey())->assertOk();
    $this->get('/admin/subcategories/'.$subcategory->getRouteKey().'/edit')->assertOk();
});

it('keeps operators, who cannot see categories, out of the subcategories pages', function (): void {
    $subcategory = Subcategory::factory()->create();
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

    $this->get('/admin/subcategories')->assertForbidden();
    $this->get('/admin/subcategories/'.$subcategory->getRouteKey())->assertForbidden();
});

it('lets admins who may only see categories view subcategories but not edit them', function (): void {
    Role::findOrCreate('category_viewer', 'admin')->givePermissionTo(['ViewAny:Category', 'View:Category']);
    $viewer = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $viewer->assignRole('category_viewer');
    $subcategory = Subcategory::factory()->create();
    $this->actingAs($viewer, 'admin');

    $this->get('/admin/subcategories/'.$subcategory->getRouteKey())->assertOk();
    $this->get('/admin/subcategories/'.$subcategory->getRouteKey().'/edit')->assertForbidden();
    livewire(ViewSubcategory::class, ['record' => $subcategory->getRouteKey()])->assertActionHidden(EditAction::class);
});
