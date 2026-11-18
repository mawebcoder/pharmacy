<?php

declare(strict_types=1);

use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Enums\TipaxPriceUnit;
use App\Modules\Shipping\Pricing\ShippingPriceCalculator;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceResponseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| Shipping prices from Tipax direct (roadmap S34, owner 2026-11-18): strategies, the calculator and Tipax's adapters.
*/

beforeEach(function (): void {
    config([
        'shipping.tipax.base_url' => 'https://tipax.test',
        'shipping.tipax.username' => 'shop',
        'shipping.tipax.password' => 'test-password',
        'shipping.tipax.api_key' => 'test-api-key',
        'shipping.tipax.retry_sleep_ms' => 0,
        'shipping.tipax.pricing.packing_id' => 30,
        'shipping.tipax.pricing.pack_type' => 4,
        'shipping.tipax.pricing.content_general_id' => 11,
        'shipping.tipax.pricing.content_liquid_id' => 12,
    ]);
    Cache::flush();
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_ORIGIN_CITY_ID => 1, StoreSettings::SHIPPING_FLAT_RATE => 500_000]);
});

function pricedParcel(int $weightG, int $value, ?BoxSpec $box = null, ParcelContent $content = ParcelContent::General, bool $estimate = false): PackedParcel
{
    return new PackedParcel(
        box: $box ?? new BoxSpec(id: 3, name: 'کارتن سایز ۳', lengthCm: 20, widthCm: 20, heightCm: 15, emptyWeightG: 150, maxWeightG: 4000, tipaxSize: 3),
        items: [['key' => 1, 'variant_id' => 1, 'title' => 'کرم', 'quantity' => 1]],
        itemsWeightG: $weightG - 150,
        totalWeightG: $weightG,
        declaredValue: $value,
        content: $content,
        isEstimate: $estimate,
    );
}

/**
 * Tipax's rate for one service family (Rate in the OM API spec).
 *
 * @return array<string, mixed>
 */
function tipaxRate(int $serviceId, float $finalPrice): array
{
    return ['serviceId' => $serviceId, 'serviceTitle' => 'سرویس '.$serviceId, 'tax' => 0, 'finalPrice' => $finalPrice, 'originalFinalPrice' => $finalPrice];
}

/**
 * Tipax answers sign-in, pricing (one entry per package) and the services between the cities.
 *
 * @param  list<array<string, mixed>>|null  $prices
 */
function fakeTipaxPricing(?array $prices = null, int $pricingStatus = 200): void
{
    Http::fake([
        'tipax.test/api/OM/v3/Account/token' => Http::response(['accessToken' => 'token-123', 'refreshToken' => 'refresh-456', 'expiresIn' => 3600]),
        'tipax.test/api/OM/v4/Pricing/GetServicesBetweenCities' => Http::response(['isSuccess' => true, 'statusCode' => 'Success', 'data' => ['serviceList' => [
            ['serviceId' => 3, 'serviceKindId' => 1, 'serviceKind' => 'عادی', 'deliveryDay' => 3],
            ['serviceId' => 1, 'serviceKindId' => 2, 'serviceKind' => 'اکسپرس', 'deliveryDay' => 1],
        ]]]),
        'tipax.test/api/OM/v4/Pricing' => Http::response($prices ?? [
            ['packNum' => 1, 'regularRate' => tipaxRate(3, 412_345), 'expressRate' => tipaxRate(1, 598_000), 'sameDayExpressRate' => null],
        ], $pricingStatus),
    ]);
}

function priceRequest(array $parcels, ?int $origin = 1): ShippingPriceRequest
{
    return new ShippingPriceRequest(originCityId: $origin, destinationCityId: 85, parcels: $parcels);
}

describe('the request adapter', function (): void {
    it('sends one Tipax package per parcel in kg, cm and rial with the codes from config', function (): void {
        $body = app(TipaxPriceRequestAdapter::class)->toBody(priceRequest([
            pricedParcel(1350, 12_500_000),
            pricedParcel(800, 3_000_000, content: ParcelContent::LiquidFragile),
        ]));

        expect($body['packageInputs'])->toHaveCount(2)
            ->and($body['packageInputs'][0])->toMatchArray([
                'origin' => ['cityId' => 1],
                'destination' => ['cityId' => 85],
                'weight' => 1.35,
                'length' => 20, 'width' => 20, 'height' => 15,
                'packageValue' => 12_500_000,
                'packingId' => 30,
                'packageContentId' => 11,
                'packType' => 4,
                'serviceId' => 0,
                'paymentType' => 10,
                'pickupType' => 20,
                'distributionType' => 10,
            ])
            ->and($body['packageInputs'][1]['packageContentId'])->toBe(12);
    });

    it('sends the default size for a parcel no box takes', function (): void {
        $parcel = new PackedParcel(null, [], 900, 900, 1_000_000, ParcelContent::General, false);

        $package = app(TipaxPriceRequestAdapter::class)->toBody(priceRequest([$parcel]))['packageInputs'][0];

        expect([$package['length'], $package['width'], $package['height']])->toBe([30, 30, 30]);
    });
});

describe('the response adapter', function (): void {
    it('offers a family once per order, priced as the sum over parcels, with its delivery days', function (): void {
        $options = (new TipaxPriceResponseAdapter(TipaxPriceUnit::Rial))->toOptions([
            ['packNum' => 1, 'regularRate' => tipaxRate(3, 400_000), 'expressRate' => tipaxRate(1, 600_000)],
            ['packNum' => 2, 'regularRate' => tipaxRate(3, 250_000), 'expressRate' => null],
        ], 2, [3 => 3], false);

        expect($options)->toHaveCount(1)
            ->and($options[0]->key)->toBe('tipax.regular')
            ->and($options[0]->method)->toBe('tipax')
            ->and($options[0]->price)->toBe(650_000)
            ->and($options[0]->deliveryDays)->toBe(3);
    });

    it('turns toman prices into rial', function (): void {
        $options = (new TipaxPriceResponseAdapter(TipaxPriceUnit::Toman))->toOptions([['regularRate' => tipaxRate(3, 41_234.5)]], 1, [], true);

        expect($options[0]->price)->toBe(412_345)
            ->and($options[0]->isEstimate)->toBeTrue();
    });
});

describe('the calculator', function (): void {
    it('offers every Tipax service on the route cheapest first, rounded up to 1,000 toman', function (): void {
        fakeTipaxPricing();

        $options = app(ShippingPriceCalculator::class)->options(priceRequest([pricedParcel(1350, 12_500_000)]));

        expect(array_map(fn (ShippingOptionData $option): array => [$option->key, $option->price, $option->deliveryDays, $option->isEstimate], $options))->toBe([
            ['tipax.regular', 420_000, 3, false],
            ['tipax.express', 600_000, 1, false],
        ]);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/Pricing') && $request->hasHeader('Authorization', 'Bearer token-123'));
    });

    it('adds the store markup before rounding', function (): void {
        fakeTipaxPricing();
        app(SaveStoreSettings::class)([StoreSettings::SHIPPING_MARKUP_PERCENT => 10]);

        $options = app(ShippingPriceCalculator::class)->options(priceRequest([pricedParcel(1350, 12_500_000)]));

        // 412,345 × 1.1 = 453,579.5 → 460,000; 598,000 × 1.1 = 657,800 → 660,000.
        expect(array_map(fn (ShippingOptionData $option): int => $option->price, $options))->toBe([460_000, 660_000]);
    });

    it('asks Tipax once for the same route and packing while the prices are kept', function (): void {
        fakeTipaxPricing();
        $calculator = app(ShippingPriceCalculator::class);

        $calculator->options(priceRequest([pricedParcel(1350, 12_500_000)]));
        $calculator->options(priceRequest([pricedParcel(1350, 12_500_000)]));
        $calculator->options(priceRequest([pricedParcel(2100, 12_500_000)]));

        Http::assertSentCount(4); // sign-in, prices, delivery days, then only prices for the heavier parcel
    });

    it('falls back to the flat rate, marked an estimate, when Tipax fails', function (int $status): void {
        fakeTipaxPricing(pricingStatus: $status);

        $options = app(ShippingPriceCalculator::class)->options(priceRequest([pricedParcel(1350, 12_500_000)]));

        expect($options)->toHaveCount(1)
            ->and($options[0]->key)->toBe('flat')
            ->and($options[0]->price)->toBe(500_000)
            ->and($options[0]->isEstimate)->toBeTrue();
    })->with([
        'server error' => [500],
        'refused' => [401],
    ]);

    it('falls back to the flat rate without asking Tipax when no origin city is set or Tipax is not configured', function (string $missing): void {
        fakeTipaxPricing();
        $missing === 'origin'
            ? app(SaveStoreSettings::class)([StoreSettings::SHIPPING_ORIGIN_CITY_ID => 0])
            : config(['shipping.tipax.password' => null]);

        $options = app(ShippingPriceCalculator::class)->options(new ShippingPriceRequest(
            originCityId: app(StoreSettings::class)->shippingOriginCityId(),
            destinationCityId: 85,
            parcels: [pricedParcel(1350, 12_500_000)],
        ));

        expect($options[0]->key)->toBe('flat');
        Http::assertNothingSent();
    })->with(['origin', 'credentials']);

    it('marks Tipax prices an estimate when a parcel was packed with defaults', function (): void {
        fakeTipaxPricing();

        $options = app(ShippingPriceCalculator::class)->options(priceRequest([pricedParcel(1350, 12_500_000, estimate: true)]));

        expect($options[0]->isEstimate)->toBeTrue();
    });
});
