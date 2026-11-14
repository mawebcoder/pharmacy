<?php

declare(strict_types=1);

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use App\Modules\System\Models\IntegrationLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
 * Tipax answers for sign-in (v3), all cities and branch cities (v4); shapes from the OM API spec.
 */
function fakeTipax(?array $cities = null): void
{
    Http::fake([
        'tipax.test/api/OM/v3/Account/token' => Http::response(['accessToken' => 'token-123', 'refreshToken' => 'refresh-456', 'expiresIn' => 3600]),
        'tipax.test/api/OM/v4/Cities/plusstate' => Http::response($cities ?? [
            ['title' => 'تهران', 'abbreviation' => 'THR', 'stateId' => 8, 'stateTitle' => 'تهران', 'jetId' => null, 'latitude' => 35.6892, 'longitude' => 51.389, 'id' => 1],
            ['title' => 'اراك', 'abbreviation' => 'ARK', 'stateId' => 1, 'stateTitle' => 'مركزي', 'jetId' => null, 'latitude' => null, 'longitude' => null, 'id' => 85],
        ]),
        'tipax.test/api/OM/v4/Cities/BranchCities' => Http::response([['title' => 'تهران', 'stateId' => 8, 'id' => 1]]),
    ]);
}

it('loads every province and city from the Tipax API with Tipax ids', function (): void {
    fakeTipax();

    $this->artisan('tipax:sync-cities')->expectsOutputToContain('شهرها: ۲')->assertSuccessful();

    expect(Province::query()->pluck(Province::COLUMN_NAME_FA, Province::COLUMN_ID)->all())->toEqualCanonicalizing([8 => 'تهران', 1 => 'مرکزی'])
        ->and(City::query()->findOrFail(85)->getNameFa())->toBe('اراک')
        ->and(City::query()->findOrFail(1)->hasTipaxBranch())->toBeTrue()
        ->and(City::query()->findOrFail(85)->hasTipaxBranch())->toBeFalse();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'Cities/plusstate') && $request->hasHeader('Authorization', 'Bearer token-123'));
});

it('signs in once and reuses the token from the cache', function (): void {
    fakeTipax();

    $this->artisan('tipax:sync-cities')->assertSuccessful();
    $this->artisan('tipax:sync-cities')->assertSuccessful();

    Http::assertSentCount(5);
});

it('logs the calls without the password, the API key or the token', function (): void {
    fakeTipax();

    $this->artisan('tipax:sync-cities')->assertSuccessful();

    $logged = IntegrationLog::query()->where('service', 'tipax')->get()->toJson();
    expect($logged)->not->toContain('test-password')
        ->and($logged)->not->toContain('test-api-key')
        ->and($logged)->not->toContain('token-123');
});

it('changes nothing when Tipax fails, is not configured or returns no cities', function (string $case): void {
    City::factory()->create([City::COLUMN_ID => 85, City::COLUMN_NAME_FA => 'اراک']);
    match ($case) {
        'refused' => Http::fake(['tipax.test/*' => Http::response(['message' => 'invalid'], 401)]),
        'not configured' => config(['shipping.tipax.password' => null]),
        'empty' => fakeTipax([]),
    };

    $this->artisan('tipax:sync-cities')->assertFailed();

    expect(City::query()->pluck(City::COLUMN_NAME_FA, City::COLUMN_ID)->all())->toBe([85 => 'اراک']);
})->with(['refused', 'not configured', 'empty']);
