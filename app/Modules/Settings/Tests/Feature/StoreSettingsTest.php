<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
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

it('saves the WhatsApp number normalised, refuses one that is not a mobile and builds the chat link', function (): void {
    $settings = app(StoreSettings::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');

    livewire(StoreSettingsPage::class)->fillForm(['whatsapp_number' => '021445566'])->call('save')->assertHasFormErrors(['whatsapp_number']);
    $before = $settings->whatsAppChatUrl('سلام');
    livewire(StoreSettingsPage::class)->fillForm(['whatsapp_number' => '۰۹۱۲ ۳۴۵ ۶۷۸۹'])->call('save')->assertHasNoFormErrors();

    expect($before)->toBeNull()
        ->and($settings->whatsAppNumber())->toBe('09123456789')
        ->and($settings->whatsAppChatUrl('سلام'))->toBe('https://wa.me/989123456789?text='.rawurlencode('سلام'));
});

it('saves the removal countdown between 0 and 10 seconds', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    $before = app(StoreSettings::class)->removalDelaySeconds();

    livewire(StoreSettingsPage::class)->fillForm(['removal_delay_seconds' => 11])->call('save')->assertHasFormErrors(['removal_delay_seconds']);
    livewire(StoreSettingsPage::class)->fillForm(['removal_delay_seconds' => 0])->call('save')->assertHasNoFormErrors();

    expect($before)->toBe(3)->and(app(StoreSettings::class)->removalDelaySeconds())->toBe(0);
});

it('saves the categories and brands pinned to the search panel in the order picked, at most 8', function (): void {
    $settings = app(StoreSettings::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    [$first, $second] = Category::factory()->count(2)->create()->all();
    $brand = Brand::factory()->create();

    livewire(StoreSettingsPage::class)
        ->fillForm(['search_category_ids' => [$second->getId(), $first->getId()], 'search_brand_ids' => [$brand->getId()]])
        ->call('save')
        ->assertHasNoFormErrors();
    livewire(StoreSettingsPage::class)
        ->fillForm(['search_brand_ids' => Brand::factory()->count(9)->create()->map->getId()->all()])
        ->call('save')
        ->assertHasFormErrors(['search_brand_ids']);

    expect($settings->searchCategoryIds())->toBe([$second->getId(), $first->getId()])
        ->and($settings->searchBrandIds())->toBe([$brand->getId()]);
});

it('saves the Telegram username from «@name» or a t.me link and refuses an invalid one', function (string $typed, ?string $saved): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');

    $page = livewire(StoreSettingsPage::class)->fillForm(['telegram_username' => $typed])->call('save');

    $saved === null ? $page->assertHasFormErrors(['telegram_username']) : $page->assertHasNoFormErrors();
    expect(app(StoreSettings::class)->telegramUsername())->toBe($saved);
})->with([
    'at sign' => ['@pharmacy_support', 'pharmacy_support'],
    'link' => ['https://t.me/pharmacy_support', 'pharmacy_support'],
    'too short' => ['abc', null],
    'persian' => ['داروخانه', null],
]);

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
