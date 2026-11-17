<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Models\Menu;
use App\Modules\Content\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active link to «پرسش‌های متداول» at the end of the header menu. States: inactive(), external().
 *
 * @extends Factory<MenuItem>
 */
final class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            MenuItem::COLUMN_MENU_ID => fn (): int => Menu::query()->where(Menu::COLUMN_LOCATION, MenuLocation::Header)->value('id')
                ?? Menu::factory()->create([Menu::COLUMN_LOCATION => MenuLocation::Header])->getId(),
            MenuItem::COLUMN_TITLE => 'پرسش‌ها',
            MenuItem::COLUMN_LINK_TYPE => LinkType::Faq,
            MenuItem::COLUMN_SORT => 100,
            MenuItem::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [MenuItem::COLUMN_IS_ACTIVE => false]);
    }

    public function external(string $url = 'https://instagram.com/hutana'): static
    {
        return $this->state(fn (array $attributes): array => [MenuItem::COLUMN_LINK_TYPE => LinkType::External, MenuItem::COLUMN_EXTERNAL_URL => $url]);
    }
}
