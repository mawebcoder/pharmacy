<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use App\Modules\Shipping\Database\Factories\ShippingFallbackRateFactory;
use App\Modules\Shipping\Enums\ShippingDistance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One weight band of the fallback shipping prices («نرخ ارسال جایگزین», roadmap S31, owner 2026-11-19): a parcel up
 * to max_weight_g pays the price for its distance (rial) when Tipax cannot price the order.
 */
final class ShippingFallbackRate extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ShippingFallbackRateFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_MAX_WEIGHT_G = 'max_weight_g';

    public const string COLUMN_SAME_CITY_PRICE = 'same_city_price';

    public const string COLUMN_SAME_PROVINCE_PRICE = 'same_province_price';

    public const string COLUMN_OTHER_PROVINCE_PRICE = 'other_province_price';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getMaxWeightG(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MAX_WEIGHT_G);
    }

    public function setMaxWeightG(int $grams): static
    {
        return $this->setAttribute(self::COLUMN_MAX_WEIGHT_G, $grams);
    }

    public function getSameCityPrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SAME_CITY_PRICE);
    }

    public function setSameCityPrice(int $price): static
    {
        return $this->setAttribute(self::COLUMN_SAME_CITY_PRICE, $price);
    }

    public function getSameProvincePrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SAME_PROVINCE_PRICE);
    }

    public function setSameProvincePrice(int $price): static
    {
        return $this->setAttribute(self::COLUMN_SAME_PROVINCE_PRICE, $price);
    }

    public function getOtherProvincePrice(): int
    {
        return (int) $this->getAttribute(self::COLUMN_OTHER_PROVINCE_PRICE);
    }

    public function setOtherProvincePrice(int $price): static
    {
        return $this->setAttribute(self::COLUMN_OTHER_PROVINCE_PRICE, $price);
    }

    public function priceFor(ShippingDistance $distance): int
    {
        return match ($distance) {
            ShippingDistance::SameCity => $this->getSameCityPrice(),
            ShippingDistance::SameProvince => $this->getSameProvincePrice(),
            ShippingDistance::OtherProvince => $this->getOtherProvincePrice(),
        };
    }

    public function activityLogLabel(): string
    {
        return 'نرخ ارسال جایگزین تا '.$this->getMaxWeightG().' گرم';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipping')
            ->logOnly([self::COLUMN_MAX_WEIGHT_G, self::COLUMN_SAME_CITY_PRICE, self::COLUMN_SAME_PROVINCE_PRICE, self::COLUMN_OTHER_PROVINCE_PRICE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_MAX_WEIGHT_G => 'integer',
            self::COLUMN_SAME_CITY_PRICE => 'integer',
            self::COLUMN_SAME_PROVINCE_PRICE => 'integer',
            self::COLUMN_OTHER_PROVINCE_PRICE => 'integer',
        ];
    }
}
