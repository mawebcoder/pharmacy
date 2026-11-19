<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Database\Seeders\ShippingFallbackRatesSeeder;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Filament\Resources\ShippingFallbackRates\Pages\ManageShippingFallbackRates;
use App\Modules\Shipping\Filament\Resources\ShippingFallbackRates\ShippingFallbackRateResource;
use App\Modules\Shipping\Models\ShippingFallbackRate;
use App\Modules\Shipping\Pricing\ShippingPriceCalculator;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/*
| «نرخ ارسال جایگزین» (roadmap S31, owner 2026-11-19): the shipping price when Tipax cannot price an order, per weight
| band and distance, and its admin page under «ارسال».
*/

beforeEach(function (): void {
    // Tipax is not configured in these tests, so every price comes from the fallbacks.
    $alborz = Province::factory()->create([Province::COLUMN_ID => 5, Province::COLUMN_NAME_FA => 'البرز']);
    $tehran = Province::factory()->create([Province::COLUMN_ID => 8, Province::COLUMN_NAME_FA => 'تهران']);
    City::factory()->for($alborz)->deliveryEnabled()->create([City::COLUMN_ID => 1199, City::COLUMN_NAME_FA => 'کرج']);
    City::factory()->for($alborz)->deliveryEnabled()->create([City::COLUMN_ID => 1200, City::COLUMN_NAME_FA => 'نظرآباد']);
    City::factory()->for($tehran)->deliveryEnabled()->create([City::COLUMN_ID => 1262, City::COLUMN_NAME_FA => 'تهران']);
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_ORIGIN_CITY_ID => 1199, StoreSettings::SHIPPING_FLAT_RATE => 500_000]);
    Http::preventStrayRequests();
});

function fallbackParcel(int $weightG): PackedParcel
{
    return new PackedParcel(new BoxSpec(3, 'کارتن سایز 3', 20, 20, 15, 150, 4000, 3), [], $weightG - 150, $weightG, 1_000_000, ParcelContent::General, false);
}

/**
 * @param  list<int>  $weights
 */
function fallbackPrice(int $destinationCityId, array $weights): array
{
    $options = app(ShippingPriceCalculator::class)->options(new ShippingPriceRequest(1199, $destinationCityId, array_map(fallbackParcel(...), $weights)));

    return [$options[0]->method, $options[0]->price];
}

it('prices each parcel from its weight band and distance, and adds them up', function (int $city, array $weights, int $price): void {
    $this->seed(ShippingFallbackRatesSeeder::class);

    expect(fallbackPrice($city, $weights))->toBe(['fallback_table', $price]);
})->with([
    'same city, 800 g' => [1199, [800], 900_000],
    'same province, exactly 1 kg' => [1200, [1000], 1_200_000],
    'other province, 1.2 kg' => [1262, [1200], 1_900_000],
    'heavier than every band' => [1262, [7500], 2_400_000],
    'two parcels' => [1199, [800, 2500], 900_000 + 1_100_000],
]);

it('charges the flat rate while the table is empty', function (): void {
    expect(fallbackPrice(1262, [800]))->toBe(['flat', 500_000]);
});

it('seeds the starting bands once and keeps the admin\'s edits', function (): void {
    $this->seed(ShippingFallbackRatesSeeder::class);
    ShippingFallbackRate::query()->where(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G, 1000)->update([ShippingFallbackRate::COLUMN_SAME_CITY_PRICE => 750_000]);
    $this->seed(ShippingFallbackRatesSeeder::class);

    expect(ShippingFallbackRate::query()->count())->toBe(3)
        ->and(ShippingFallbackRate::query()->where(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G, 1000)->value(ShippingFallbackRate::COLUMN_SAME_CITY_PRICE))->toBe(750_000);
});

describe('the admin page', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('sits in «ارسال» and lets a super admin add and edit a band in toman, kept in rial', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        $existing = ShippingFallbackRate::factory()->create();

        expect(ShippingFallbackRateResource::getNavigationGroup())->toBe('ارسال');

        livewire(ManageShippingFallbackRates::class)
            ->assertCanSeeTableRecords([$existing])
            ->callAction(TestAction::make(CreateAction::class), [
                ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => 3000,
                ShippingFallbackRate::COLUMN_SAME_CITY_PRICE => '110,000',
                ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE => '150,000',
                ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE => '190,000',
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make(EditAction::class)->table($existing), [ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE => '160,000'])
            ->assertHasNoFormErrors();

        expect(ShippingFallbackRate::query()->where(ShippingFallbackRate::COLUMN_MAX_WEIGHT_G, 3000)->sole()->getOtherProvincePrice())->toBe(1_900_000)
            ->and($existing->refresh()->getOtherProvincePrice())->toBe(1_600_000);
    });

    it('refuses a weight that already has a band', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        ShippingFallbackRate::factory()->create([ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => 1000]);

        livewire(ManageShippingFallbackRates::class)
            ->callAction(TestAction::make(CreateAction::class), [
                ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => 1000,
                ShippingFallbackRate::COLUMN_SAME_CITY_PRICE => '90,000',
                ShippingFallbackRate::COLUMN_SAME_PROVINCE_PRICE => '120,000',
                ShippingFallbackRate::COLUMN_OTHER_PROVINCE_PRICE => '150,000',
            ])
            ->assertHasFormErrors([ShippingFallbackRate::COLUMN_MAX_WEIGHT_G => 'unique']);
    });

    it('is closed to operators', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->create()->assignRole(AdminRole::Operator->value), 'admin');

        $this->get(ShippingFallbackRateResource::getUrl())->assertForbidden();
    });
});
