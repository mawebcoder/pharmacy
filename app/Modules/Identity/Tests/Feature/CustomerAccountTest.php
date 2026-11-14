<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\DeleteAddress;
use App\Modules\Identity\Actions\SaveAddress;
use App\Modules\Identity\Data\AddressData;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Enums\Gender;
use App\Modules\Identity\Exceptions\AddressRefused;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Identity\Filament\Resources\Customers\RelationManagers\AddressesRelationManager;
use App\Modules\Identity\Livewire\Account\Addresses;
use App\Modules\Identity\Livewire\Account\Profile;
use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->tehran = Province::factory()->create([Province::COLUMN_ID => 8, Province::COLUMN_NAME_FA => 'تهران']);
    $this->city = City::factory()->for($this->tehran)->deliveryEnabled()->create([City::COLUMN_ID => 1, City::COLUMN_NAME_FA => 'تهران']);
    $this->customer = Customer::factory()->create();
});

/**
 * A valid address form for the delivery city, with overrides.
 *
 * @return array<string, mixed>
 */
function addressForm(array $overrides = []): array
{
    return [
        Address::COLUMN_TITLE => 'خانه',
        Address::COLUMN_RECEIVER_NAME => 'سارا رضایی',
        Address::COLUMN_RECEIVER_MOBILE => '۰۹۱۲۳۴۵۶۷۸۹',
        Address::COLUMN_PROVINCE_ID => 8,
        Address::COLUMN_CITY_ID => 1,
        Address::COLUMN_FULL_ADDRESS => 'خیابان ولیعصر، کوچه نسترن',
        Address::COLUMN_PLAQUE => '۱۲',
        Address::COLUMN_UNIT => '',
        Address::COLUMN_FLOOR => '',
        Address::COLUMN_POSTAL_CODE => '1234567890',
        Address::COLUMN_IS_DEFAULT => false,
        ...$overrides,
    ];
}

it('sends guests from the account pages to sign in', function (string $path): void {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/account', '/account/addresses', '/account/profile', '/account/orders', '/account/reviews', '/account/wishlist']);

it('shows the account pages to a signed-in customer, with empty states', function (string $path, string $text): void {
    $this->actingAs($this->customer, 'customer')->get($path)->assertOk()->assertSee($text);
})->with([
    'dashboard' => ['/account', 'آدرس پیش‌فرض'],
    'addresses' => ['/account/addresses', 'هنوز آدرسی ثبت نکرده‌اید.'],
    'orders' => ['/account/orders', 'هنوز سفارشی ثبت نکرده‌اید.'],
    'reviews' => ['/account/reviews', 'نظرات من'],
    'wishlist' => ['/account/wishlist', 'هنوز محصولی را ذخیره نکرده‌اید.'],
]);

describe('addresses', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->customer, 'customer');
    });

    it('adds the first address as the default, with Persian digits normalised', function (): void {
        livewire(Addresses::class)->call('create')->set('form', addressForm())->call('save')->assertHasNoErrors();

        $address = Address::query()->ownedBy($this->customer->getId())->sole();
        expect($address->isDefault())->toBeTrue()
            ->and($address->getReceiverMobile())->toBe('09123456789')
            ->and($address->getPlaque())->toBe('12')
            ->and($address->getCityId())->toBe(1);
    });

    it('refuses a city without delivery, a city of another province and bad codes', function (array $overrides, string $field): void {
        $off = City::factory()->for($this->tehran)->create([City::COLUMN_ID => 112]);
        $other = City::factory()->deliveryEnabled()->create([City::COLUMN_ID => 85]);
        $overrides = array_map(fn (mixed $value): mixed => match ($value) {
            'off' => $off->getId(),
            'other' => $other->getId(),
            default => $value,
        }, $overrides);

        livewire(Addresses::class)->call('create')->set('form', addressForm($overrides))->call('save')->assertHasErrors(['form.'.$field]);

        expect(Address::query()->count())->toBe(0);
    })->with([
        'delivery off' => [[Address::COLUMN_CITY_ID => 'off'], Address::COLUMN_CITY_ID],
        'other province' => [[Address::COLUMN_CITY_ID => 'other'], Address::COLUMN_CITY_ID],
        'postal code' => [[Address::COLUMN_POSTAL_CODE => '0123456789'], Address::COLUMN_POSTAL_CODE],
        'mobile' => [[Address::COLUMN_RECEIVER_MOBILE => '021445566'], Address::COLUMN_RECEIVER_MOBILE],
    ]);

    it('moves the default to the chosen address and back to the newest when the default is deleted', function (): void {
        $first = Address::factory()->for($this->customer)->default()->create();
        $second = Address::factory()->for($this->customer)->create();

        livewire(Addresses::class)->call('makeDefault', $second->getId());
        $afterSwitch = [$first->fresh()->isDefault(), $second->fresh()->isDefault()];
        livewire(Addresses::class)->call('delete', $second->getId());

        expect($afterSwitch)->toBe([false, true])
            ->and($second->fresh()->trashed())->toBeTrue()
            ->and($first->fresh()->isDefault())->toBeTrue();
    });

    it('keeps at most 10 addresses', function (): void {
        Address::factory()->count(Address::MAX_PER_CUSTOMER)->for($this->customer)->create();

        livewire(Addresses::class)->call('create')->set('form', addressForm())->call('save')->assertHasErrors(['form.'.Address::COLUMN_TITLE]);

        expect(Address::query()->ownedBy($this->customer->getId())->count())->toBe(Address::MAX_PER_CUSTOMER);
    });

    it('never shows or changes another customer\'s address', function (): void {
        $theirs = Address::factory()->for(Customer::factory())->create();

        livewire(Addresses::class)->call('edit', $theirs->getId())->assertNotFound();

        expect(fn () => app(DeleteAddress::class)($this->customer, $theirs))->toThrow(AddressRefused::class)
            ->and(fn () => app(SaveAddress::class)($this->customer, AddressData::fromArray(addressForm()), $theirs))->toThrow(AddressRefused::class)
            ->and($theirs->fresh())->not->toBeNull();
    });
});

describe('profile', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->customer, 'customer');
    });

    it('saves the profile with the national id encrypted and the birth date from the Jalali picker', function (): void {
        livewire(Profile::class)
            ->set('form.first_name', 'سارا')
            ->set('form.last_name', 'رضایی')
            ->set('form.national_id', '۰۰۸۴۵۷۵۹۴۸')
            ->set('form.birth_date', '1990-03-21')
            ->set('form.gender', 'female')
            ->call('save')
            ->assertHasNoErrors();

        $customer = $this->customer->fresh();
        expect($customer->getNationalId())->toBe('0084575948')
            ->and($customer->getBirthDate()?->toDateString())->toBe('1990-03-21')
            ->and($customer->getGender())->toBe(Gender::Female)
            ->and(DB::table('customers')->where('id', $customer->getId())->value('national_id'))->not->toBe('0084575948');
    });

    it('refuses a wrong national id and a birth date in the future', function (string $field, string $value): void {
        livewire(Profile::class)
            ->set('form.first_name', 'سارا')
            ->set('form.last_name', 'رضایی')
            ->set('form.'.$field, $value)
            ->call('save')
            ->assertHasErrors(['form.'.$field]);
    })->with([
        'national id checksum' => ['national_id', '1234567890'],
        'future birth date' => ['birth_date', now()->addYear()->toDateString()],
    ]);
});

it('shows a customer\'s addresses read-only on their admin page', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    $address = Address::factory()->for($this->customer)->create([Address::COLUMN_TITLE => 'محل کار']);

    livewire(AddressesRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$address])
        ->assertSee('تهران، تهران');
});
