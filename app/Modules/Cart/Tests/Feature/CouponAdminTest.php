<?php

declare(strict_types=1);

use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Filament\Resources\Coupons\CouponForm;
use App\Modules\Cart\Filament\Resources\Coupons\CouponResource;
use App\Modules\Cart\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Modules\Cart\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

function couponAdmin(?AdminRole $role = null): AdminUser
{
    $factory = AdminUser::factory()->withTwoFactorAuthentication();

    return ($role instanceof AdminRole ? $factory->withRole($role) : $factory->superAdmin())->create();
}

it('creates a capped percent coupon limited to a brand and a product picked by SKU', function (): void {
    $this->actingAs(couponAdmin(), 'admin');
    $brand = Brand::factory()->create();
    $variant = ProductVariant::factory()->for(Product::factory()->active())->create();

    livewire(CreateCoupon::class)
        ->fillForm([
            Coupon::COLUMN_CODE => 'bahar10',
            Coupon::COLUMN_TYPE => CouponType::Percent->value,
            Coupon::COLUMN_VALUE => 10,
            Coupon::COLUMN_MAX_DISCOUNT => '150,000',
            Coupon::COLUMN_MIN_ORDER_AMOUNT => '300,000',
            Coupon::COLUMN_USAGE_LIMIT_PER_CUSTOMER => 1,
            'brand_ids' => [$brand->getId()],
            CouponForm::SKU_IDS => [$variant->getId()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::query()->sole();
    expect($coupon->getCode())->toBe('BAHAR10')
        ->and($coupon->getMaxDiscount())->toBe(1_500_000)
        ->and($coupon->getMinOrderAmount())->toBe(3_000_000)
        ->and($coupon->includedIds('coupon_brands'))->toBe([$brand->getId()])
        ->and($coupon->includedIds('coupon_products'))->toBe([$variant->getProductId()]);
});

it('saves a fixed coupon typed in toman and shows it again when editing', function (): void {
    $this->actingAs(couponAdmin(), 'admin');

    livewire(CreateCoupon::class)
        ->fillForm([Coupon::COLUMN_CODE => 'GIFT', Coupon::COLUMN_TYPE => CouponType::Fixed->value])
        ->fillForm([CouponForm::FIXED_VALUE => '50,000'])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::query()->sole();
    expect($coupon->getType())->toBe(CouponType::Fixed)
        ->and($coupon->getValue())->toBe(500_000);

    livewire(EditCoupon::class, ['record' => $coupon->getKey()])
        ->assertSchemaStateSet([CouponForm::FIXED_VALUE => 50_000]);
});

it('refuses a percent above 100 and a code that is taken or malformed', function (array $fields, string $field): void {
    $this->actingAs(couponAdmin(), 'admin');
    Coupon::factory()->create([Coupon::COLUMN_CODE => 'TAKEN']);

    livewire(CreateCoupon::class)
        ->fillForm([Coupon::COLUMN_CODE => 'NEW1', Coupon::COLUMN_TYPE => CouponType::Percent->value, Coupon::COLUMN_VALUE => 10, ...$fields])
        ->call('create')
        ->assertHasFormErrors([$field]);
})->with([
    'percent over 100' => [[Coupon::COLUMN_VALUE => 120], Coupon::COLUMN_VALUE],
    'taken code' => [[Coupon::COLUMN_CODE => 'taken'], Coupon::COLUMN_CODE],
    'code with spaces inside' => [[Coupon::COLUMN_CODE => 'تخفیف'], Coupon::COLUMN_CODE],
]);

it('lets operators look coupons up but not create them, and keeps them from content editors', function (): void {
    $this->actingAs(couponAdmin(AdminRole::Operator), 'admin')->get(CouponResource::getUrl('index'))->assertOk();
    $this->actingAs(couponAdmin(AdminRole::Operator), 'admin')->get(CouponResource::getUrl('create'))->assertForbidden();
    $this->actingAs(couponAdmin(AdminRole::ContentEditor), 'admin')->get(CouponResource::getUrl('index'))->assertForbidden();
});
