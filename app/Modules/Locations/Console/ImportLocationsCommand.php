<?php

declare(strict_types=1);

namespace App\Modules\Locations\Console;

use App\Modules\Locations\Actions\DownloadLocationDataset;
use App\Modules\Locations\Actions\ImportLocations;
use App\Modules\Locations\Data\LocationDatasetData;
use App\Modules\Locations\Exceptions\InvalidLocationDataset;
use Illuminate\Console\Command;

/**
 * php artisan locations:import (roadmap D11.5). Run again after a dataset update, then tipax:sync-cities (S22).
 */
final class ImportLocationsCommand extends Command
{
    protected $signature = 'locations:import
        {--path= : A directory with provinces.json, counties.json, cities.json and county-centers.json (skips the download)}';

    protected $description = 'Import Iran\'s provinces, counties and cities from the pinned location dataset';

    public function handle(DownloadLocationDataset $download, ImportLocations $import): int
    {
        try {
            $directory = $this->option('path') ?: $download();
            $result = $import(LocationDatasetData::fromDirectory((string) $directory));
        } catch (InvalidLocationDataset $invalid) {
            $this->components->error($invalid->getMessage());

            return self::FAILURE;
        }

        activity('locations')
            ->event('locations_imported')
            ->withProperties([
                'version' => config('locations.dataset.version'),
                'commit' => config('locations.dataset.commit'),
                'provinces' => $result->provinces,
                'counties' => $result->counties,
                'cities' => $result->cities,
            ])
            ->log('locations_imported');

        $this->components->info("استان‌ها: {$result->provinces} · شهرستان‌ها: {$result->counties} · شهرها: {$result->cities} (با مختصات: {$result->citiesWithCoordinates})");

        return self::SUCCESS;
    }
}
