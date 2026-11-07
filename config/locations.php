<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Location Dataset
    |--------------------------------------------------------------------------
    |
    | Provinces, counties and cities come from sajaddp/list-of-cities-in-Iran
    | (roadmap D4.1), pinned to one commit so every import gets the same
    | data. The files are downloaded at import time and never committed:
    | the dataset is GPL-3.0 and its use is still [CONFIRM] (roadmap D10.7).
    | County-centre coordinates come from GeoNames (CC BY 4.0, attribution
    | required).
    |
    */

    'dataset' => [
        'version' => 'v3.1.2',
        'base_url' => env('LOCATIONS_DATASET_URL', 'https://raw.githubusercontent.com/sajaddp/list-of-cities-in-Iran'),
        'commit' => env('LOCATIONS_DATASET_COMMIT', 'ace21bb251f96ced350bbc01541acffd71121feb'),
        'files' => [
            'provinces' => 'dist/json/provinces.json',
            'counties' => 'dist/json/counties.json',
            'cities' => 'dist/json/cities-filtered.json',
            'county_centers' => 'dist/json/county-centers.json',
        ],
        'storage_path' => storage_path('app/private/locations'),
        'timeout' => 60,
    ],

    'coordinates_attribution' => 'مختصات مراکز شهرستان‌ها: GeoNames (CC BY 4.0)',

];
