<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeWidget>
 */
final class HomeWidgetFactory extends Factory
{
    protected $model = HomeWidget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            HomeWidget::COLUMN_TYPE => HomeWidgetType::Trust,
            HomeWidget::COLUMN_SORT => 0,
            HomeWidget::COLUMN_IS_ACTIVE => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function ofType(HomeWidgetType $type, array $settings = [], ?string $title = null): static
    {
        return $this->state(fn (array $attributes): array => [
            HomeWidget::COLUMN_TYPE => $type,
            HomeWidget::COLUMN_SETTINGS => $settings,
            HomeWidget::COLUMN_TITLE_FA => $title,
        ]);
    }
}
