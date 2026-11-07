<?php

declare(strict_types=1);

use App\Modules\Locations\Models\City;
use App\Modules\Locations\Models\County;
use App\Modules\Locations\Models\Province;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

function fixtureDataset(): string
{
    return __DIR__.'/../Fixtures/dataset';
}

it('imports provinces, counties and cities from a dataset directory', function (): void {
    $this->artisan('locations:import', ['--path' => fixtureDataset()])
        ->expectsOutputToContain('شهرها: 4')
        ->assertSuccessful();

    expect(Province::count())->toBe(2)
        ->and(County::count())->toBe(3)
        ->and(City::count())->toBe(4);

    $tajrish = City::query()->where(City::COLUMN_NAME_FA, 'تجریش')->firstOrFail();
    expect($tajrish->getProvince()->getNameFa())->toBe('تهران')
        ->and($tajrish->getCounty()->getNameFa())->toBe('شمیرانات')
        ->and($tajrish->isDeliveryEnabled())->toBeFalse();
});

it('gives county centres their coordinates and leaves other cities without', function (): void {
    $this->artisan('locations:import', ['--path' => fixtureDataset()])->assertSuccessful();

    $tehran = City::query()->where(City::COLUMN_NAME_FA, 'تهران')->firstOrFail();
    $tajrish = City::query()->where(City::COLUMN_NAME_FA, 'تجریش')->firstOrFail();

    expect($tehran->getLatitude())->toBe(35.75)
        ->and($tehran->getLongitude())->toBe(51.5148)
        ->and($tajrish->getLatitude())->toBeNull();
});

it('updates existing rows on a re-run and keeps the delivery switches', function (): void {
    $this->artisan('locations:import', ['--path' => fixtureDataset()])->assertSuccessful();
    City::query()->where(City::COLUMN_NAME_FA, 'اراک')->firstOrFail()->setIsDeliveryEnabled(true)->save();

    $this->artisan('locations:import', ['--path' => fixtureDataset()])->assertSuccessful();

    expect(City::count())->toBe(4)
        ->and(City::query()->where(City::COLUMN_NAME_FA, 'اراک')->firstOrFail()->isDeliveryEnabled())->toBeTrue();
});

it('records one activity entry per import instead of one per row', function (): void {
    $this->artisan('locations:import', ['--path' => fixtureDataset()])->assertSuccessful();

    $activities = Activity::query()->where('log_name', 'locations')->get();
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->event)->toBe('locations_imported')
        ->and($activities->first()->properties->get('cities'))->toBe(4);
});

it('refuses a dataset with a missing file, a broken row or an unknown parent', function (string $file, string $contents, string $message): void {
    $directory = storage_path('framework/testing/locations-'.uniqid());
    File::copyDirectory(fixtureDataset(), $directory);
    $contents === '' ? File::delete("{$directory}/{$file}") : File::put("{$directory}/{$file}", $contents);

    $this->artisan('locations:import', ['--path' => $directory])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(City::count())->toBe(0);
    File::deleteDirectory($directory);
})->with([
    'missing file' => ['cities.json', '', 'not found'],
    'not a list' => ['provinces.json', '{"id": 1}', 'not a JSON list'],
    'row without a name' => ['counties.json', '[{"id": 1, "slug": "x", "province_id": 100}]', 'missing or wrong "name"'],
    'unknown county' => ['cities.json', '[{"id": 1, "name": "x", "slug": "x", "province_id": 100, "county_id": 999}]', 'county_id 999 does not exist'],
]);

it('downloads the pinned dataset when no path is given', function (): void {
    $storage = storage_path('framework/testing/locations-download-'.uniqid());
    config(['locations.dataset.storage_path' => $storage, 'locations.dataset.commit' => 'abc123']);
    Http::fake([
        '*/abc123/dist/json/provinces.json' => Http::response(File::get(fixtureDataset().'/provinces.json')),
        '*/abc123/dist/json/counties.json' => Http::response(File::get(fixtureDataset().'/counties.json')),
        '*/abc123/dist/json/cities-filtered.json' => Http::response(File::get(fixtureDataset().'/cities.json')),
        '*/abc123/dist/json/county-centers.json' => Http::response(File::get(fixtureDataset().'/county-centers.json')),
    ]);

    $this->artisan('locations:import')->assertSuccessful();

    expect(City::count())->toBe(4)
        ->and(File::exists("{$storage}/abc123/cities.json"))->toBeTrue();
    Http::assertSentCount(4);
    File::deleteDirectory($storage);
});

it('reports a failed download', function (): void {
    config(['locations.dataset.storage_path' => storage_path('framework/testing/locations-fail-'.uniqid())]);
    Http::fake(['*' => Http::response('', 404)]);

    $this->artisan('locations:import')
        ->expectsOutputToContain('HTTP 404')
        ->assertFailed();
});
