<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\CategoriesSeeder;
use App\Modules\Catalog\Database\Seeders\PopularCollectionSeeder;
use App\Modules\Catalog\Database\Seeders\SearchShortcutsSeeder;
use App\Modules\Catalog\Database\Seeders\StandardAttributesSeeder;
use App\Modules\Content\Database\Seeders\DraftPagesSeeder;
use App\Modules\Content\Database\Seeders\MenusSeeder;
use App\Modules\Identity\Database\Seeders\SuperAdminSeeder;
use App\Modules\Shipping\Database\Seeders\ShippingFallbackRatesSeeder;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Starting data (migrations only create schema; owner, 2026-11-17). Each seeder adds only what is missing.
        $this->call([
            SuperAdminSeeder::class,
            StandardAttributesSeeder::class,
            CategoriesSeeder::class,
            PopularCollectionSeeder::class,
            SearchShortcutsSeeder::class,
            DraftPagesSeeder::class,
            MenusSeeder::class,
            TipaxCartonsSeeder::class,
            ShippingFallbackRatesSeeder::class,
        ]);
    }
}
