<?php

declare(strict_types=1);

use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Models\TipaxReference;
use App\Modules\Shipping\Queries\TipaxReferences;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| `tipax:ping`, `tipax:sync-reference` and the Tipax codes picked in the store settings (roadmap S33, owner 2026-11-19).
| Answers shaped as Tipax's test API gave them.
*/

beforeEach(function (): void {
    config([
        'shipping.tipax.base_url' => 'https://tipax.test',
        'shipping.tipax.username' => 'shop',
        'shipping.tipax.password' => 'test-password',
        'shipping.tipax.api_key' => 'test-api-key',
        'shipping.tipax.retry_sleep_ms' => 0,
    ]);
    Cache::flush();
});

/**
 * @param  array<string, mixed>  $overrides  URL pattern => response, replacing the default answer
 */
function fakeTipaxReference(array $overrides = []): void
{
    Http::fake(array_merge([
        'tipax.test/api/OM/v3/Account/token' => Http::response(['accessToken' => 'token-123', 'expiresIn' => 3600]),
        'tipax.test/api/OM/v4/Customers/Wallet' => Http::response('125000.0'),
        'tipax.test/api/OM/v4/Customers' => Http::response(['customerId' => 21825, 'fullName' => 'هوتانا', 'logoUrl' => null, 'mobile' => null, 'apiKey' => 'test-api-key']),
        'tipax.test/api/OM/v4/Pricing/GetServicesList' => Http::response(['data' => [['title' => 'ارسال زمینی همان روز', 'isActive' => true, 'id' => 1], ['title' => 'همان روز', 'isActive' => true, 'id' => 12]], 'isSuccess' => true]),
        'tipax.test/api/OM/v4/PackingType/GetPackTypes' => Http::response(['data' => [['title' => 'بسته', 'latinTitle' => 'Package', 'id' => 20], ['title' => 'پاکت', 'latinTitle' => 'Envelope', 'id' => 10]], 'isSuccess' => true]),
        'tipax.test/api/OM/v4/PackingType/GetPackingTypes' => Http::response(['totalCount' => 2, 'result' => [['title' => 'نیاز به بسته بندی ندارد', 'packType' => 20, 'id' => 504], ['title' => 'پاکت A4 لمینت', 'packType' => 10, 'id' => 13]]]),
        'tipax.test/api/OM/v4/PackingType/GetPackagingContents' => Http::response(['totalCount' => 3, 'result' => [['title' => 'عمومی', 'packType' => 20, 'id' => 9], ['title' => 'مایعات-شکستنی', 'packType' => 20, 'id' => 1], ['title' => 'عمومی', 'packType' => 10, 'id' => 3]]]),
    ], $overrides));
}

it('pings Tipax with the account name and wallet, never its API key', function (): void {
    fakeTipaxReference();

    $this->artisan('tipax:ping')
        ->expectsOutputToContain('حساب «هوتانا» (شماره ۲۱۸۲۵) · موجودی کیف پول ۱۲۵,۰۰۰')
        ->doesntExpectOutputToContain('test-api-key')
        ->assertSuccessful();
});

it('fails the ping when Tipax refuses the credentials', function (): void {
    fakeTipaxReference(['tipax.test/api/OM/v3/Account/token' => Http::response(['message' => 'invalid'], 401)]);

    $this->artisan('tipax:ping')->assertFailed();
});

it('copies Tipax\'s lists with their pack types, and keeps entries Tipax stops listing', function (): void {
    fakeTipaxReference();
    TipaxReference::query()->insert([TipaxReference::COLUMN_TYPE => 'packing', TipaxReference::COLUMN_EXTERNAL_ID => 999, TipaxReference::COLUMN_TITLE => 'قدیمی', 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('tipax:sync-reference')->expectsOutputToContain('سرویس: ۲ · نوع بسته: ۲ · بسته‌بندی: ۲ · محتوای بسته: ۳')->assertSuccessful();

    expect(app(TipaxReferences::class)->options(TipaxReferenceType::Content, 20))->toBe([1 => 'مایعات-شکستنی (1)', 9 => 'عمومی (9)'])
        ->and(app(TipaxReferences::class)->options(TipaxReferenceType::Packing, 10))->toBe([13 => 'پاکت A4 لمینت (13)'])
        ->and(TipaxReference::query()->where(TipaxReference::COLUMN_EXTERNAL_ID, 999)->exists())->toBeTrue();
});

it('changes nothing when one of the lists fails', function (): void {
    fakeTipaxReference(['tipax.test/api/OM/v4/PackingType/GetPackagingContents' => Http::response('error', 500)]);

    $this->artisan('tipax:sync-reference')->assertFailed();

    expect(TipaxReference::query()->count())->toBe(0);
});

it('prices with the Tipax codes picked in the store settings, else the .env defaults', function (): void {
    $parcel = new PackedParcel(new BoxSpec(3, 'کارتن سایز 3', 20, 20, 15, 150, 4000, 3), [], 1200, 1350, 1_000_000, ParcelContent::LiquidFragile, false);
    $request = new ShippingPriceRequest(1199, 1262, [$parcel]);

    $defaults = app(TipaxPriceRequestAdapter::class)->toBody($request, 1)['packageInputs'][0];
    app(SaveStoreSettings::class)([StoreSettings::TIPAX_PACK_TYPE => 10, StoreSettings::TIPAX_PACKING_ID => 13, StoreSettings::TIPAX_CONTENT_GENERAL_ID => 3, StoreSettings::TIPAX_CONTENT_LIQUID_ID => 3]);
    $picked = app(TipaxPriceRequestAdapter::class)->toBody($request, 1)['packageInputs'][0];

    expect([$defaults['packType'], $defaults['packingId'], $defaults['packageContentId']])->toBe([20, 504, 1])
        ->and([$picked['packType'], $picked['packingId'], $picked['packageContentId']])->toBe([10, 13, 3]);
});
