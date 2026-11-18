<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use App\Modules\Shipping\Database\Factories\ShippingBoxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A box we pack orders in (roadmap S31 «کارتن‌ها», owner 2026-11-17): inner sizes in cm, its own weight in g (with
 * filler), the most a parcel in it may weigh (box included, at most 6 kg), and Tipax's carton number when it is one of their standard cartons. Each SKU says how many of it fit the box.
 */
final class ShippingBox extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ShippingBoxFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME = 'name';

    public const string COLUMN_TIPAX_SIZE = 'tipax_size';

    public const string COLUMN_LENGTH_CM = 'length_cm';

    public const string COLUMN_WIDTH_CM = 'width_cm';

    public const string COLUMN_HEIGHT_CM = 'height_cm';

    public const string COLUMN_EMPTY_WEIGHT_G = 'empty_weight_g';

    /**
     * The most a parcel in this box may weigh, box included (owner, 2026-11-17); never above Tapin's 6 kg.
     */
    public const string COLUMN_MAX_WEIGHT_G = 'max_weight_g';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME);
    }

    public function setName(string $name): static
    {
        return $this->setAttribute(self::COLUMN_NAME, $name);
    }

    public function getTipaxSize(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_TIPAX_SIZE);

        return $value === null ? null : (int) $value;
    }

    public function setTipaxSize(?int $size): static
    {
        return $this->setAttribute(self::COLUMN_TIPAX_SIZE, $size);
    }

    public function getLengthCm(): int
    {
        return (int) $this->getAttribute(self::COLUMN_LENGTH_CM);
    }

    public function setLengthCm(int $length): static
    {
        return $this->setAttribute(self::COLUMN_LENGTH_CM, $length);
    }

    public function getWidthCm(): int
    {
        return (int) $this->getAttribute(self::COLUMN_WIDTH_CM);
    }

    public function setWidthCm(int $width): static
    {
        return $this->setAttribute(self::COLUMN_WIDTH_CM, $width);
    }

    public function getHeightCm(): int
    {
        return (int) $this->getAttribute(self::COLUMN_HEIGHT_CM);
    }

    public function setHeightCm(int $height): static
    {
        return $this->setAttribute(self::COLUMN_HEIGHT_CM, $height);
    }

    public function getEmptyWeightG(): int
    {
        return (int) $this->getAttribute(self::COLUMN_EMPTY_WEIGHT_G);
    }

    public function setEmptyWeightG(int $weight): static
    {
        return $this->setAttribute(self::COLUMN_EMPTY_WEIGHT_G, $weight);
    }

    public function getMaxWeightG(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MAX_WEIGHT_G);
    }

    public function setMaxWeightG(int $weight): static
    {
        return $this->setAttribute(self::COLUMN_MAX_WEIGHT_G, $weight);
    }

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $isActive);
    }

    public function volumeCm3(): int
    {
        return $this->getLengthCm() * $this->getWidthCm() * $this->getHeightCm();
    }

    /**
     * «۲۰ × ۱۵ × ۱۰» style label of the inner sizes.
     */
    public function sizesLabel(): string
    {
        return $this->getLengthCm().' × '.$this->getWidthCm().' × '.$this->getHeightCm();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    public function activityLogLabel(): string
    {
        return 'کارتن: '.$this->getName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipping')
            ->logOnly([self::COLUMN_NAME, self::COLUMN_LENGTH_CM, self::COLUMN_WIDTH_CM, self::COLUMN_HEIGHT_CM, self::COLUMN_EMPTY_WEIGHT_G, self::COLUMN_MAX_WEIGHT_G, self::COLUMN_IS_ACTIVE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
