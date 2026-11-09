<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Models\BannerPlacement;
use App\Modules\Content\Models\HomeWidget;
use Illuminate\Support\Facades\DB;

/**
 * Removes a widget from the home page; a slider's banners stay in the library.
 */
final class DeleteHomeWidget
{
    public function __invoke(HomeWidget $widget): void
    {
        DB::transaction(function () use ($widget): void {
            BannerPlacement::query()
                ->where(BannerPlacement::COLUMN_PLACE, BannerPlace::HomeWidget->value)
                ->where(BannerPlacement::COLUMN_PLACE_ID, $widget->getId())
                ->get()
                ->each->delete();
            $widget->delete();
        });
    }
}
