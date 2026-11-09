<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\BannerPlacementFactory;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One banner shown in one place, at a position (roadmap D3.6, S13b). place_id is the home widget, category,
 * collection, brand or tag id; null for the list pages.
 */
final class BannerPlacement extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<BannerPlacementFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_BANNER_ID = 'banner_id';

    public const string COLUMN_PLACE = 'place';

    public const string COLUMN_PLACE_ID = 'place_id';

    public const string COLUMN_SORT = 'sort';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getBannerId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_BANNER_ID);
    }

    public function setBannerId(int $bannerId): static
    {
        return $this->setAttribute(self::COLUMN_BANNER_ID, $bannerId);
    }

    public function getPlace(): BannerPlace
    {
        return $this->getAttribute(self::COLUMN_PLACE);
    }

    public function setPlace(BannerPlace $place): static
    {
        return $this->setAttribute(self::COLUMN_PLACE, $place);
    }

    public function getPlaceId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_PLACE_ID);

        return $value === null ? null : (int) $value;
    }

    public function setPlaceId(?int $placeId): static
    {
        return $this->setAttribute(self::COLUMN_PLACE_ID, $placeId);
    }

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
    }

    /**
     * @return BelongsTo<Banner, $this>
     */
    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }

    public function getBanner(): ?Banner
    {
        return $this->getRelationValue('banner');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_BANNER_ID, self::COLUMN_PLACE, self::COLUMN_PLACE_ID, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'جایگاه بنر: '.$this->getPlace()->label();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_BANNER_ID => 'integer',
            self::COLUMN_PLACE => BannerPlace::class,
            self::COLUMN_PLACE_ID => 'integer',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
