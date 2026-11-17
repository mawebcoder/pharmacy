<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Settings\Filament\Resources\Pharmacists\Pages\ManagePharmacists;
use App\Modules\Settings\Filament\Resources\Pharmacists\PharmacistResource;
use App\Modules\Settings\Models\Pharmacist;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| The pharmacist in charge (roadmap S35; B2 rule 3): on /pharmacist first, and named on every product page.
*/

it('shows the pharmacist in charge first on /pharmacist, with the council number', function (): void {
    Pharmacist::factory()->create([Pharmacist::COLUMN_FULL_NAME => 'دکتر علی رضایی', Pharmacist::COLUMN_SORT => 1]);
    Pharmacist::factory()->responsible()->create([Pharmacist::COLUMN_FULL_NAME => 'دکتر مریم احمدی', Pharmacist::COLUMN_LICENSE_NUMBER => '12345', Pharmacist::COLUMN_SORT => 2]);

    $this->get(route('pharmacist'))
        ->assertOk()
        ->assertSeeInOrder(['داروساز مسئول', 'دکتر مریم احمدی', '۱۲۳۴۵', 'دکتر علی رضایی']);
});

it('names the pharmacist in charge on every product page, and leaves the line out without one', function (): void {
    $product = Product::factory()->active()->create([Product::COLUMN_SLUG => 'vitamin-d']);
    ProductVariant::factory()->for($product)->create();

    livewire('catalog::product-page', ['slug' => 'vitamin-d'])->assertDontSee('زیر نظر');

    Pharmacist::factory()->responsible()->create([Pharmacist::COLUMN_FULL_NAME => 'دکتر مریم احمدی', Pharmacist::COLUMN_LICENSE_NUMBER => '12345']);

    livewire('catalog::product-page', ['slug' => 'vitamin-d'])
        ->assertSee(['زیر نظر دکتر مریم احمدی', '۱۲۳۴۵'])
        ->assertSeeHtml('href="'.route('pharmacist').'"');
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    });

    it('keeps one pharmacist in charge: a new one takes it over', function (): void {
        $before = Pharmacist::factory()->responsible()->create();

        livewire(ManagePharmacists::class)
            ->callAction(TestAction::make(CreateAction::class), [
                Pharmacist::COLUMN_FULL_NAME => 'دکتر سارا کریمی',
                Pharmacist::COLUMN_LICENSE_NUMBER => '۹۸۷۶۵',
                Pharmacist::COLUMN_IS_RESPONSIBLE => true,
            ])
            ->assertHasNoFormErrors();

        expect($before->fresh()->isResponsible())->toBeFalse()
            ->and(Pharmacist::query()->responsible()->sole())
            ->getFullName()->toBe('دکتر سارا کریمی')
            ->getLicenseNumber()->toBe('98765');
    });

    it('takes only digits as the council number', function (): void {
        livewire(ManagePharmacists::class)
            ->callAction(TestAction::make(CreateAction::class), [Pharmacist::COLUMN_FULL_NAME => 'دکتر سارا کریمی', Pharmacist::COLUMN_LICENSE_NUMBER => 'abc'])
            ->assertHasFormErrors([Pharmacist::COLUMN_LICENSE_NUMBER => 'regex']);
    });

    it('keeps the pharmacists from operators', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin')
            ->get(PharmacistResource::getUrl('index'))
            ->assertForbidden();
    });
});
