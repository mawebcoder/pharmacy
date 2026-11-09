<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\BannerPlacement;
use App\Modules\Content\Models\HomeWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannerPlacement>
 */
final class BannerPlacementFactory extends Factory
{
    protected $model = BannerPlacement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            BannerPlacement::COLUMN_BANNER_ID => Banner::factory(),
            BannerPlacement::COLUMN_PLACE => BannerPlace::HomeWidget,
            BannerPlacement::COLUMN_PLACE_ID => HomeWidget::factory()->ofType(HomeWidgetType::Slider),
            BannerPlacement::COLUMN_SORT => 1,
        ];
    }
}
