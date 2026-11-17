<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A menu at a location (the migration already creates one per location; tests that need a fresh one delete those).
 *
 * @extends Factory<Menu>
 */
final class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Menu::COLUMN_LOCATION => MenuLocation::Footer1,
            Menu::COLUMN_TITLE => 'راهنمای خرید',
        ];
    }
}
