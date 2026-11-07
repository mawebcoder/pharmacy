<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Brands\Pages\CreateBrand;
use App\Modules\Catalog\Filament\Resources\Brands\Pages\EditBrand;
use App\Modules\Catalog\Filament\Resources\Brands\Pages\ListBrands;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

describe('as a super admin', function (): void {
    beforeEach(function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    });

    it('creates a brand from the form with a slug from the English name', function (): void {
        livewire(CreateBrand::class)
            ->fillForm([
                Brand::COLUMN_NAME_FA => 'لاروش پوزای',
                Brand::COLUMN_NAME_EN => 'La Roche-Posay',
                Brand::COLUMN_COUNTRY => 'FR',
                Brand::COLUMN_IS_ACTIVE => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $brand = Brand::query()->sole();
        expect($brand->getSlug())->toBe('la-roche-posay')
            ->and($brand->getCountryName())->toBe('فرانسه')
            ->and(Activity::query()->whereMorphedTo('subject', $brand)->where('event', 'created')->exists())->toBeTrue();
    });

    it('shows a form error for a slug another brand uses', function (): void {
        Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);

        livewire(CreateBrand::class)
            ->fillForm([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_SLUG => 'eucerin'])
            ->call('create')
            ->assertHasFormErrors([Brand::COLUMN_SLUG => 'unique']);

        expect(Brand::query()->count())->toBe(1);
    });

    it('edits a brand and keeps its slug', function (): void {
        $brand = Brand::factory()->create([Brand::COLUMN_SLUG => 'eucerin']);

        livewire(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->fillForm([Brand::COLUMN_NAME_FA => 'اوسرین آلمان', Brand::COLUMN_DESCRIPTION => 'مراقبت پوست'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($brand->fresh())
            ->getNameFa()->toBe('اوسرین آلمان')
            ->getDescription()->toBe('مراقبت پوست')
            ->getSlug()->toBe('eucerin');
    });

    it('switches a brand off from the list', function (): void {
        $brand = Brand::factory()->create();

        livewire(ListBrands::class)
            ->call('updateTableColumnState', Brand::COLUMN_IS_ACTIVE, (string) $brand->getKey(), false);

        expect($brand->fresh()->isActive())->toBeFalse();
    });

    it('finds brands by Persian or English name', function (): void {
        $eucerin = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'اوسرین', Brand::COLUMN_NAME_EN => 'Eucerin']);
        $cinere = Brand::factory()->create([Brand::COLUMN_NAME_FA => 'سینره', Brand::COLUMN_NAME_EN => 'Cinere']);

        livewire(ListBrands::class)->searchTable('Eucerin')->assertCanSeeTableRecords([$eucerin])->assertCanNotSeeTableRecords([$cinere]);
        livewire(ListBrands::class)->searchTable('سینره')->assertCanSeeTableRecords([$cinere])->assertCanNotSeeTableRecords([$eucerin]);
    });

    it('moves a brand to the trash and restores it', function (): void {
        $brand = Brand::factory()->create();

        livewire(EditBrand::class, ['record' => $brand->getRouteKey()])->callAction(DeleteAction::class);
        expect($brand->fresh()->trashed())->toBeTrue();

        livewire(EditBrand::class, ['record' => $brand->getRouteKey()])->callAction(RestoreAction::class);
        expect($brand->fresh()->trashed())->toBeFalse();
    });
});

it('lets a content editor create and edit brands but not delete them', function (): void {
    $editor = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create();
    $brand = Brand::factory()->create();
    $this->actingAs($editor, 'admin');

    $this->get('/admin/brands')->assertOk();
    $this->get('/admin/brands/create')->assertOk();

    livewire(EditBrand::class, ['record' => $brand->getRouteKey()])->assertActionHidden(DeleteAction::class);
});

it('keeps operators out of the brands page', function (): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();

    $this->actingAs($operator, 'admin')->get('/admin/brands')->assertForbidden();
});
