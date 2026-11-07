<?php

declare(strict_types=1);

namespace App\Modules\Locations\Data;

/**
 * Switches delivery on or off for a set of cities.
 */
final readonly class CityDeliveryData
{
    /**
     * @param  list<int>  $cityIds
     */
    public function __construct(
        public array $cityIds,
        public bool $isDeliveryEnabled,
    ) {}

    /**
     * @param  iterable<int|string>  $cityIds
     */
    public static function for(iterable $cityIds, bool $isDeliveryEnabled): self
    {
        $ids = [];

        foreach ($cityIds as $cityId) {
            $ids[] = (int) $cityId;
        }

        return new self(array_values(array_unique($ids)), $isDeliveryEnabled);
    }
}
