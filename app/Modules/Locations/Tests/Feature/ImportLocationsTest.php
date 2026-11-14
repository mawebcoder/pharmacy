<?php

declare(strict_types=1);

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\Province;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;

function tipaxFixture(string $name): string
{
    return __DIR__."/../Fixtures/tipax/{$name}.json";
}

it('imports provinces and cities with Tipax ids from Tipax\'s city list', function (): void {
    $this->artisan('locations:import-tipax', ['file' => tipaxFixture('plusstate')])
        ->expectsOutputToContain('شهرها: ۴')
        ->assertSuccessful();

    $tajrish = City::query()->findOrFail(112);

    expect(Province::query()->pluck(Province::COLUMN_NAME_FA, Province::COLUMN_ID)->all())->toEqualCanonicalizing([8 => 'تهران', 1 => 'مرکزی'])
        ->and(City::count())->toBe(4)
        ->and($tajrish->getNameFa())->toBe('تجریش')
        ->and($tajrish->getProvince()->getId())->toBe(8)
        ->and($tajrish->isDeliveryEnabled())->toBeFalse();
});

it('keeps Tipax coordinates and treats 0 or null as unknown', function (): void {
    $this->artisan('locations:import-tipax', ['file' => tipaxFixture('plusstate')])->assertSuccessful();

    expect(City::query()->findOrFail(1)->getLatitude())->toBe(35.6892)
        ->and(City::query()->findOrFail(112)->getLatitude())->toBeNull()
        ->and(City::query()->findOrFail(140)->getLongitude())->toBeNull();
});

it('marks the cities that have a Tipax branch', function (): void {
    $this->artisan('locations:import-tipax', ['file' => tipaxFixture('plusstate'), '--branches' => tipaxFixture('branches')])->assertSuccessful();

    expect(City::query()->where(City::COLUMN_HAS_TIPAX_BRANCH, true)->pluck(City::COLUMN_ID)->all())->toEqualCanonicalizing([1, 85]);
});

it('updates names on a re-run, keeps the delivery switches and keeps cities Tipax no longer lists', function (): void {
    $this->artisan('locations:import-tipax', ['file' => tipaxFixture('plusstate')])->assertSuccessful();
    City::query()->findOrFail(85)->setIsDeliveryEnabled(true)->setNameFa('نام قدیمی')->save();
    $shorter = storage_path('framework/testing/plusstate-'.uniqid().'.json');
    File::put($shorter, json_encode(array_slice(json_decode((string) File::get(tipaxFixture('plusstate')), true), 0, 3)));

    $this->artisan('locations:import-tipax', ['file' => $shorter])
        ->expectsOutputToContain('در تیپاکس نیستند: ۱')
        ->assertSuccessful();

    $arak = City::query()->findOrFail(85);
    expect($arak->getNameFa())->toBe('اراک')
        ->and($arak->isDeliveryEnabled())->toBeTrue()
        ->and(City::query()->find(140))->not->toBeNull();
    File::delete($shorter);
});

it('records one activity entry per import instead of one per row', function (): void {
    $this->artisan('locations:import-tipax', ['file' => tipaxFixture('plusstate')])->assertSuccessful();

    $activities = Activity::query()->where('log_name', 'locations')->get();
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->event)->toBe('tipax_cities_imported')
        ->and($activities->first()->properties->get('cities'))->toBe(4);
});

it('refuses a missing file, a file that is not a list or a broken row, and saves nothing', function (?string $contents, string $message): void {
    $path = storage_path('framework/testing/plusstate-'.uniqid().'.json');

    if ($contents !== null) {
        File::put($path, $contents);
    }

    $this->artisan('locations:import-tipax', ['file' => $path])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(City::count())->toBe(0);
    File::delete($path);
})->with([
    'missing file' => [null, 'not found'],
    'not a list' => ['{"id": 1}', 'not a JSON list'],
    'row without a name' => ['[{"id": 1, "stateId": 8, "stateTitle": "تهران"}]', 'missing or wrong "title"'],
    'row without a province' => ['[{"id": 1, "title": "تهران"}]', 'missing or wrong "stateId"'],
]);
