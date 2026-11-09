<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Models\BannerPlacement;
use Illuminate\Support\Facades\DB;

/**
 * Sets the banners of one place in order (at most 6), replacing what was there. A page place needs its item (the
 * category, brand, …); the list pages and home widgets pass their own id or null.
 */
final class SetPlaceBanners
{
    /**
     * @param  list<int>  $bannerIds  in display order
     *
     * @throws BannerChangeRefused
     */
    public function __invoke(BannerPlace $place, ?int $placeId, array $bannerIds): void
    {
        $bannerIds = array_values(array_unique($bannerIds));

        if (count($bannerIds) > BannerPlace::MAX_BANNERS) {
            throw BannerChangeRefused::tooMany(BannerPlace::MAX_BANNERS);
        }

        if (($place->catalogType() !== null || $place === BannerPlace::HomeWidget) && $placeId === null) {
            throw BannerChangeRefused::placeItemMissing();
        }

        DB::transaction(function () use ($place, $placeId, $bannerIds): void {
            BannerPlacement::query()
                ->where(BannerPlacement::COLUMN_PLACE, $place->value)
                ->where(BannerPlacement::COLUMN_PLACE_ID, $placeId)
                ->get()
                ->each->delete();

            foreach ($bannerIds as $position => $bannerId) {
                (new BannerPlacement)
                    ->setBannerId($bannerId)
                    ->setPlace($place)
                    ->setPlaceId($placeId)
                    ->setSort($position + 1)
                    ->save();
            }
        });
    }
}
