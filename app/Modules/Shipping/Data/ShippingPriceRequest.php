<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * What every shipping price strategy prices (roadmap S34): the origin city (our warehouse; null while the admin has not
 * set it), the destination city (Tipax ids, D4) and the parcels the packer made. Strategies turn it into their
 * service's request through an adapter.
 */
final readonly class ShippingPriceRequest
{
    /**
     * @param  list<PackedParcel>  $parcels
     */
    public function __construct(
        public ?int $originCityId,
        public int $destinationCityId,
        public array $parcels,
    ) {}

    /**
     * True when any parcel was packed with defaults (a missing weight, or no box takes an item).
     */
    public function isEstimate(): bool
    {
        foreach ($this->parcels as $parcel) {
            if ($parcel->isEstimate || ! $parcel->box instanceof BoxSpec) {
                return true;
            }
        }

        return false;
    }

    /**
     * A key that changes whenever the price could: the route and each parcel's box, sizes, weight, value and content.
     */
    public function cacheKey(): string
    {
        $parcels = array_map(fn (PackedParcel $parcel): array => [
            $parcel->box?->id,
            $parcel->box?->lengthCm,
            $parcel->box?->widthCm,
            $parcel->box?->heightCm,
            $parcel->totalWeightG,
            $parcel->declaredValue,
            $parcel->content->value,
            $parcel->tipaxContentId,
        ], $this->parcels);

        return hash('xxh128', (string) json_encode([$this->originCityId, $this->destinationCityId, $parcels]));
    }
}
