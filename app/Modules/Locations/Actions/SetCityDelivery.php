<?php

declare(strict_types=1);

namespace App\Modules\Locations\Actions;

use App\Modules\Locations\Data\CityDeliveryData;
use App\Modules\Locations\Models\City;
use Illuminate\Support\Facades\DB;

/**
 * Switches delivery on or off for cities. Each city that actually changes is saved through the model,
 * so the change lands in the audit log.
 */
final class SetCityDelivery
{
    /**
     * @return int The number of cities that changed.
     */
    public function __invoke(CityDeliveryData $data): int
    {
        return DB::transaction(function () use ($data): int {
            $cities = City::query()
                ->whereKey($data->cityIds)
                ->where(City::COLUMN_IS_DELIVERY_ENABLED, ! $data->isDeliveryEnabled)
                ->lockForUpdate()
                ->get();

            foreach ($cities as $city) {
                $city->setIsDeliveryEnabled($data->isDeliveryEnabled)->save();
            }

            return $cities->count();
        });
    }
}
