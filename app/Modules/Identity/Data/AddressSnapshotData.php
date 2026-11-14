<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Support\AddressText;

/**
 * A delivery address as an order keeps it (roadmap D3.4 «Snapshot rule», S25): every part copied, with the province
 * and city names and Tipax ids, so editing the address or the city later never changes a past order.
 */
final readonly class AddressSnapshotData
{
    public function __construct(
        public int $addressId,
        public string $title,
        public string $receiverName,
        public string $receiverMobile,
        public int $provinceId,
        public string $provinceName,
        public int $cityId,
        public string $cityName,
        public ?string $mainStreet,
        public ?string $sideStreet,
        public ?string $alley,
        public ?string $block,
        public ?string $fullAddress,
        public string $plaque,
        public ?string $unit,
        public ?string $floor,
        public string $postalCode,
        public bool $isDeliveryEnabled,
    ) {}

    /**
     * The address text (AddressText): one line or labelled sections.
     */
    public function text(): AddressText
    {
        return AddressText::from([
            'province' => $this->provinceName,
            'city' => $this->cityName,
            'main_street' => $this->mainStreet,
            'side_street' => $this->sideStreet,
            'alley' => $this->alley,
            'block' => $this->block,
            'plaque' => $this->plaque,
            'floor' => $this->floor,
            'unit' => $this->unit,
            'details' => $this->fullAddress,
            'postal_code' => $this->postalCode,
            'receiver_name' => $this->receiverName,
            'receiver_mobile' => $this->receiverMobile,
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'address_id' => $this->addressId,
            'title' => $this->title,
            'receiver_name' => $this->receiverName,
            'receiver_mobile' => $this->receiverMobile,
            'province_id' => $this->provinceId,
            'province' => $this->provinceName,
            'city_id' => $this->cityId,
            'city' => $this->cityName,
            'main_street' => $this->mainStreet,
            'side_street' => $this->sideStreet,
            'alley' => $this->alley,
            'block' => $this->block,
            'details' => $this->fullAddress,
            'plaque' => $this->plaque,
            'unit' => $this->unit,
            'floor' => $this->floor,
            'postal_code' => $this->postalCode,
        ];
    }
}
