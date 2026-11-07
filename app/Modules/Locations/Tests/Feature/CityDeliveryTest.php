<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Locations\Actions\SetCityDelivery;
use App\Modules\Locations\Data\CityDeliveryData;
use App\Modules\Locations\Filament\Resources\Cities\Pages\ListCities;
use App\Modules\Locations\Models\City;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('switches delivery on only for the cities that change and logs each change', function (): void {
    $off = City::factory()->create();
    $alreadyOn = City::factory()->deliveryEnabled()->create();

    $changed = app(SetCityDelivery::class)(CityDeliveryData::for([$off->getId(), $alreadyOn->getId()], true));

    expect($changed)->toBe(1)
        ->and($off->fresh()->isDeliveryEnabled())->toBeTrue()
        ->and(Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $off)->sole()->attribute_changes->toArray())
        ->toEqual(['attributes' => ['is_delivery_enabled' => true], 'old' => ['is_delivery_enabled' => false]])
        ->and(Activity::query()->whereMorphedTo('subject', $alreadyOn)->where('event', 'updated')->exists())->toBeFalse();
});

describe('cities admin page', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('lets an operator list cities and switch delivery', function (): void {
        $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
        $city = City::factory()->create([City::COLUMN_NAME_FA => 'تجریش']);
        $this->actingAs($operator, 'admin');

        $this->get('/admin/cities')->assertOk()->assertSee('تجریش')->assertSee('GeoNames');

        livewire(ListCities::class)
            ->call('updateTableColumnState', City::COLUMN_IS_DELIVERY_ENABLED, (string) $city->getKey(), true);

        expect($city->fresh()->isDeliveryEnabled())->toBeTrue();
    });

    it('lets an operator enable delivery for several cities at once', function (): void {
        $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
        $cities = City::factory()->count(3)->create();
        $this->actingAs($operator, 'admin');

        livewire(ListCities::class)->callTableBulkAction('enableDelivery', $cities);

        expect(City::deliveryEnabled()->count())->toBe(3);
    });

    it('forbids content editors from the cities page', function (): void {
        $editor = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create();

        $this->actingAs($editor, 'admin')->get('/admin/cities')->assertForbidden();
    });
});
