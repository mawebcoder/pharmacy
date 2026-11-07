<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\StandardAttributesSeeder;
use App\Modules\Identity\Database\Seeders\SuperAdminSeeder;
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
        $this->call([SuperAdminSeeder::class, StandardAttributesSeeder::class]);
    }
}
