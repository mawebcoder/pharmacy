<?php

declare(strict_types=1);

namespace App\Modules\Locations\Actions;

use App\Modules\Locations\Exceptions\InvalidLocationDataset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the pinned dataset files (config/locations.php) into storage and returns that directory.
 * Files already downloaded for the same commit are reused.
 */
final class DownloadLocationDataset
{
    private const array LOCAL_NAMES = [
        'provinces' => 'provinces.json',
        'counties' => 'counties.json',
        'cities' => 'cities.json',
        'county_centers' => 'county-centers.json',
    ];

    #[\NoDiscard]
    public function __invoke(): string
    {
        /** @var array{base_url: string, commit: string, files: array<string, string>, storage_path: string, timeout: int} $dataset */
        $dataset = config('locations.dataset');
        $directory = rtrim($dataset['storage_path'], '/').'/'.$dataset['commit'];

        File::ensureDirectoryExists($directory);

        foreach (self::LOCAL_NAMES as $key => $localName) {
            $target = "{$directory}/{$localName}";

            if (is_file($target)) {
                continue;
            }

            $url = rtrim($dataset['base_url'], '/')."/{$dataset['commit']}/{$dataset['files'][$key]}";
            $response = Http::timeout($dataset['timeout'])->retry(2, 500, throw: false)->get($url);

            if (! $response->successful()) {
                throw InvalidLocationDataset::downloadFailed($url, $response->status());
            }

            File::put($target, $response->body());
        }

        return $directory;
    }
}
