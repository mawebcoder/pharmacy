<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * One end of a Tipax shipment (roadmap S35): who and where, with Tipax's city id and a map point (the city's when the
 * exact one is unknown).
 */
final readonly class TipaxPartyData
{
    public function __construct(
        public string $name,
        public string $mobile,
        public int $cityId,
        public string $address,
        public ?string $postalCode,
        public ?string $plaque = null,
        public ?string $unit = null,
        public ?string $floor = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
    ) {}

    /**
     * @return array<string, mixed> Tipax's ClientWithAddress
     */
    public function toTipax(): array
    {
        return array_filter([
            'cityId' => $this->cityId,
            'fullAddress' => $this->address,
            'postalCode' => $this->postalCode,
            'no' => $this->plaque,
            'unit' => $this->unit,
            'floor' => $this->floor,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'beneficiary' => ['fullName' => $this->name, 'mobile' => $this->mobile],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
