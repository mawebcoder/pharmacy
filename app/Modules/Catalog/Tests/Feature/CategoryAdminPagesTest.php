<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Categories\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Filament\Resources\Categories\Pages\ManageCategoryTree;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\DeleteAction;
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

    it('creates a subcategory from the form', function (): void {
        $parent = Category::factory()->create();

        livewire(CreateCategory::class)
            ->fillForm([
                Category::COLUMN_PARENT_ID => $parent->getId(),
                Category::COLUMN_NAME_FA => 'ویتامین‌ها',
                Category::COLUMN_NAME_EN => 'Vitamins',
                Category::COLUMN_IS_ACTIVE => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::query()->where(Category::COLUMN_SLUG, 'vitamins')->sole()->getParentId())->toBe($parent->getId());
    });

    it('offers only categories that can still hold subcategories as parents', function (): void {
        $root = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
        $middle = Category::factory()->childOf($root)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
        $leaf = Category::factory()->childOf($middle)->create([Category::COLUMN_NAME_FA => 'ویتامین D']);

        livewire(CreateCategory::class)
            ->assertFormFieldExists(Category::COLUMN_PARENT_ID, fn ($field): bool => $field->getOptions() === [
                $root->getId() => 'مکمل‌ها',
                $middle->getId() => 'مکمل‌ها › ویتامین‌ها',
            ]);

        expect($leaf->exists)->toBeTrue();
    });

    it('shows the refusal and saves nothing when a move would make a fourth level', function (): void {
        $supplements = Category::factory()->create();
        $vitamins = Category::factory()->childOf($supplements)->create();
        Category::factory()->childOf($vitamins)->create();
        $beauty = Category::factory()->create();
        $skin = Category::factory()->childOf($beauty)->create();

        livewire(EditCategory::class, ['record' => $vitamins->getRouteKey()])
            ->fillForm([Category::COLUMN_PARENT_ID => $skin->getId()])
            ->call('save')
            ->assertNotified();

        expect($vitamins->fresh()->getParentId())->toBe($supplements->getId());
    });

    it('refuses to delete a category that has subcategories', function (): void {
        $root = Category::factory()->create();
        Category::factory()->childOf($root)->create();

        livewire(EditCategory::class, ['record' => $root->getRouteKey()])->callAction(DeleteAction::class);

        expect($root->fresh()->trashed())->toBeFalse();
    });

    it('saves a drag and drop from the tree page', function (): void {
        $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
        $second = Category::factory()->create([Category::COLUMN_SORT => 2]);

        livewire(ManageCategoryTree::class)
            ->call('updateTree', [['id' => $second->getId(), 'children' => [['id' => $first->getId(), 'children' => []]]]])
            ->assertNotified('ترتیب دسته‌ها ذخیره شد.');

        expect($first->fresh()->getParentId())->toBe($second->getId());
    });

    it('finds categories by name or parent name', function (): void {
        $supplements = Category::factory()->create([Category::COLUMN_NAME_FA => 'مکمل‌ها']);
        $vitamins = Category::factory()->childOf($supplements)->create([Category::COLUMN_NAME_FA => 'ویتامین‌ها']);
        $beauty = Category::factory()->create([Category::COLUMN_NAME_FA => 'آرایشی']);

        livewire(ListCategories::class)->searchTable('مکمل')->assertCanSeeTableRecords([$supplements, $vitamins])->assertCanNotSeeTableRecords([$beauty]);
    });
});

it('lets a content editor reorder and edit categories but not delete them', function (): void {
    $editor = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create();
    $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
    $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
    $this->actingAs($editor, 'admin');

    $this->get('/admin/categories')->assertOk();

    livewire(ManageCategoryTree::class)->call('updateTree', [['id' => $second->getId()], ['id' => $first->getId()]]);
    livewire(EditCategory::class, ['record' => $first->getRouteKey()])->assertActionHidden(DeleteAction::class);

    expect($second->fresh()->getSort())->toBe(1);
});

it('keeps operators out of the categories pages', function (): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();

    $this->actingAs($operator, 'admin')->get('/admin/categories')->assertForbidden();
});

it('lets only admins who may edit categories save the tree', function (): void {
    Role::findOrCreate('category_viewer', 'admin')->givePermissionTo('ViewAny:Category');
    $viewer = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $viewer->assignRole('category_viewer');
    $first = Category::factory()->create([Category::COLUMN_SORT => 1]);
    $second = Category::factory()->create([Category::COLUMN_SORT => 2]);
    $this->actingAs($viewer, 'admin');

    livewire(ManageCategoryTree::class)
        ->call('updateTree', [['id' => $second->getId()], ['id' => $first->getId()]])
        ->assertForbidden();

    expect($first->fresh()->getSort())->toBe(1);
});
