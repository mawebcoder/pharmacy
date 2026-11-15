<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\Categories\RelationManagers\SubcategoriesRelationManager;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
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

    it('creates a category from the form', function (): void {
        livewire(CreateCategory::class)
            ->fillForm([
                Category::COLUMN_NAME_FA => 'مکمل‌ها',
                Category::COLUMN_NAME_EN => 'Supplements',
                Category::COLUMN_IS_ACTIVE => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::query()->where(Category::COLUMN_SLUG, 'supplements')->exists())->toBeTrue();
    });

    it('refuses to delete a category that has subcategories', function (): void {
        $category = Category::factory()->create();
        Subcategory::factory()->of($category)->create();

        livewire(EditCategory::class, ['record' => $category->getRouteKey()])->callAction(DeleteAction::class);

        expect($category->fresh()->trashed())->toBeFalse();
    });

    it('saves a drag and drop from the categories table', function (): void {
        $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
        $second = Category::factory()->create([Category::COLUMN_SORT => 2]);

        livewire(ListCategories::class)
            ->call('reorderTable', [$second->getId(), $first->getId()])
            ->assertNotified('ترتیب دسته‌ها ذخیره شد.');

        expect($second->fresh()->getSort())->toBe(1)
            ->and($first->fresh()->getSort())->toBe(2);
    });

    it('finds categories by name', function (): void {
        $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
        $beauty = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرایشی']);

        livewire(ListCategories::class)->searchTable('مکمل')->assertCanSeeTableRecords([$supplements])->assertCanNotSeeTableRecords([$beauty]);
    });

    it('adds a subcategory in «زیردسته‌ها»', function (): void {
        $category = Category::factory()->create();

        livewire(SubcategoriesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('create')->table(), [
                Subcategory::COLUMN_NAME_FA => 'ویتامین‌ها',
                Subcategory::COLUMN_NAME_EN => 'Vitamins',
                Subcategory::COLUMN_IS_ACTIVE => true,
            ])
            ->assertHasNoFormErrors();

        expect($category->subcategories()->sole())
            ->getNameFa()->toBe('ویتامین‌ها')
            ->getSlug()->toBe('vitamins');
    });

    it('shows the refusal and keeps a subcategory that has products', function (): void {
        $subcategory = Subcategory::factory()->create();
        Product::factory()->for($subcategory, 'primarySubcategory')->create();

        livewire(SubcategoriesRelationManager::class, ['ownerRecord' => $subcategory->category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('delete')->table($subcategory))
            ->assertNotified();

        expect($subcategory->fresh()->trashed())->toBeFalse();
    });
});

it('lets a content editor reorder and edit categories but not delete them', function (): void {
    $editor = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create();
    $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
    $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
    $this->actingAs($editor, 'admin');

    $this->get('/admin/categories')->assertOk();

    livewire(ListCategories::class)->call('reorderTable', [$second->getId(), $first->getId()]);
    livewire(EditCategory::class, ['record' => $first->getRouteKey()])->assertActionHidden(DeleteAction::class);

    expect($second->fresh()->getSort())->toBe(1);
});

it('keeps operators out of the categories pages', function (): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();

    $this->actingAs($operator, 'admin')->get('/admin/categories')->assertForbidden();
});

it('lets only admins who may edit categories reorder them', function (): void {
    Role::findOrCreate('category_viewer', 'admin')->givePermissionTo('ViewAny:Category');
    $viewer = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $viewer->assignRole('category_viewer');
    $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
    $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
    $this->actingAs($viewer, 'admin');

    livewire(ListCategories::class)
        ->call('reorderTable', [$second->getId(), $first->getId()])
        ->assertForbidden();

    expect($first->fresh()->getSort())->toBe(1);
});
