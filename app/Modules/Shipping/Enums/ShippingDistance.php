<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * How far a parcel goes from our warehouse, for the fallback shipping prices (roadmap S31, owner 2026-11-19).
 */
enum ShippingDistance: string implements HasLabel
{
    use LabelledEnum;

    case SameCity = 'same_city';
    case SameProvince = 'same_province';
    case OtherProvince = 'other_province';

    public function label(): string
    {
        return match ($this) {
            self::SameCity => 'درون شهر',
            self::SameProvince => 'درون استان',
            self::OtherProvince => 'استان دیگر',
        };
    }
}
