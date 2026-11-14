<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Filament\Pages\StoreSettingsPage;
use App\Modules\Settings\Queries\StoreSettings;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

it('uses the defaults until the admin saves, then the saved values, typed in toman', function (): void {
    $settings = app(StoreSettings::class);
    $before = [$settings->maxQuantityPerOrder(), $settings->freeShippingThreshold()];
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');

    livewire(StoreSettingsPage::class)
        ->fillForm(['max_quantity_per_order' => 4, 'free_shipping_threshold' => '750,000'])
        ->call('save')
        ->assertNotified('تنظیمات ذخیره شد.');

    expect($before)->toBe([10, 5_000_000])
        ->and([$settings->maxQuantityPerOrder(), $settings->freeShippingThreshold()])->toBe([4, 7_500_000]);
});

it('shows the free-shipping amount in the announcement bar, and nothing when it is 0', function (): void {
    $this->get('/')->assertSee('ارسال رایگان برای سفارش‌های بالای ۵۰۰٬۰۰۰ تومان');

    app(SaveStoreSettings::class)([StoreSettings::FREE_SHIPPING_THRESHOLD => 0]);

    $this->get('/')->assertDontSee('ارسال رایگان برای سفارش‌های بالای');
});

it('keeps content editors off the settings page', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin')
        ->get(StoreSettingsPage::getUrl())
        ->assertForbidden();
});
