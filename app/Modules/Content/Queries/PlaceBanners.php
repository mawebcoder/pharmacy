<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\BannerPlacement;
use Illuminate\Database\Eloquent\Collection;

/**
 * The banners a place shows now, in order: visible banners whose target still exists, at most 6.
 */
final readonly class PlaceBanners
{
    /**
     * @return Collection<int, Banner>
     */
    public function for(BannerPlace $place, ?int $placeId = null): Collection
    {
        $banners = BannerPlacement::query()
            ->where(BannerPlacement::COLUMN_PLACE, $place->value)
            ->where(BannerPlacement::COLUMN_PLACE_ID, $placeId)
            ->whereHas('banner', fn ($banners) => $banners->visible())
            ->with('banner.media')
            ->orderBy(BannerPlacement::COLUMN_SORT)
            ->limit(BannerPlace::MAX_BANNERS)
            ->get()
            ->map(fn (BannerPlacement $placement): ?Banner => $placement->getBanner())
            ->filter(fn (?Banner $banner): bool => $banner instanceof Banner && $banner->linkUrl() !== null);

        return new Collection($banners->values()->all());
    }

    /**
     * Ids of the banners placed in a place, in order, for the admin.
     *
     * @return list<int>
     */
    public function ids(BannerPlace $place, ?int $placeId = null): array
    {
        return array_values(BannerPlacement::query()
            ->where(BannerPlacement::COLUMN_PLACE, $place->value)
            ->where(BannerPlacement::COLUMN_PLACE_ID, $placeId)
            ->orderBy(BannerPlacement::COLUMN_SORT)
            ->pluck(BannerPlacement::COLUMN_BANNER_ID)
            ->map(intval(...))
            ->all());
    }
}
