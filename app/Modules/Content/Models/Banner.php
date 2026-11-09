<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Catalog\Queries\CatalogLinks;
use App\Modules\Content\Database\Factories\BannerFactory;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A banner in the library («بنرها», roadmap D3.6, S13b): three artworks (desktop 1920×600, tablet 1536×768,
 * mobile 800×1000), a link target the admin picked, and a schedule. Placed on the home page and list pages through
 * banner_placements; one banner can be reused in several places.
 */
final class Banner extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_LINK_TYPE = 'link_type';

    public const string COLUMN_LINK_ID = 'link_id';

    public const string COLUMN_EXTERNAL_URL = 'external_url';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_STARTS_AT = 'starts_at';

    public const string COLUMN_ENDS_AT = 'ends_at';

    public const string IMAGE_DESKTOP = 'desktop';

    public const string IMAGE_TABLET = 'tablet';

    public const string IMAGE_MOBILE = 'mobile';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    /**
     * Admin name; also the image's alt text.
     */
    public function getTitle(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE);
    }

    public function setTitle(string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE, $title);
    }

    public function getLinkType(): BannerLinkType
    {
        return $this->getAttribute(self::COLUMN_LINK_TYPE);
    }

    public function setLinkType(BannerLinkType $type): static
    {
        return $this->setAttribute(self::COLUMN_LINK_TYPE, $type);
    }

    public function getLinkId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_LINK_ID);

        return $value === null ? null : (int) $value;
    }

    public function setLinkId(?int $linkId): static
    {
        return $this->setAttribute(self::COLUMN_LINK_ID, $linkId);
    }

    public function getExternalUrl(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_EXTERNAL_URL);

        return $value === null ? null : (string) $value;
    }

    public function setExternalUrl(?string $url): static
    {
        return $this->setAttribute(self::COLUMN_EXTERNAL_URL, $url);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function getIsActive(): bool
    {
        return $this->isActive();
    }

    public function setIsActive(bool $isActive): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $isActive);
    }

    public function getStartsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_STARTS_AT);
    }

    public function setStartsAt(?Carbon $startsAt): static
    {
        return $this->setAttribute(self::COLUMN_STARTS_AT, $startsAt);
    }

    public function getEndsAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_ENDS_AT);
    }

    public function setEndsAt(?Carbon $endsAt): static
    {
        return $this->setAttribute(self::COLUMN_ENDS_AT, $endsAt);
    }

    /**
     * Where the banner leads now, or null when its target is gone or hidden (the banner is then not shown).
     */
    public function linkUrl(): ?string
    {
        $type = $this->getLinkType();

        return match ($type) {
            BannerLinkType::CategoriesList => route('categories.index'),
            BannerLinkType::CollectionsList => route('collections.index'),
            BannerLinkType::BrandsList => route('brands.index'),
            BannerLinkType::External => $this->getExternalUrl(),
            default => $this->getLinkId() === null ? null : app(CatalogLinks::class)->url($type->value, $this->getLinkId()),
        };
    }

    public function opensInNewTab(): bool
    {
        return $this->getLinkType() === BannerLinkType::External;
    }

    public function getDesktopImage(): ?StoredImage
    {
        return $this->image(self::IMAGE_DESKTOP);
    }

    public function getTabletImage(): ?StoredImage
    {
        return $this->image(self::IMAGE_TABLET);
    }

    public function getMobileImage(): ?StoredImage
    {
        return $this->image(self::IMAGE_MOBILE);
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [
            self::IMAGE_DESKTOP => 'banner_desktop',
            self::IMAGE_TABLET => 'banner_tablet',
            self::IMAGE_MOBILE => 'banner_mobile',
        ];
    }

    /**
     * @return HasMany<BannerPlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(BannerPlacement::class);
    }

    /**
     * Banners a shopper may see now: active and inside their schedule.
     *
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true)
            ->where(fn (Builder $starts) => $starts->whereNull(self::COLUMN_STARTS_AT)->orWhere(self::COLUMN_STARTS_AT, '<=', now()))
            ->where(fn (Builder $ends) => $ends->whereNull(self::COLUMN_ENDS_AT)->orWhere(self::COLUMN_ENDS_AT, '>', now()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_TITLE, self::COLUMN_LINK_TYPE, self::COLUMN_LINK_ID, self::COLUMN_EXTERNAL_URL, self::COLUMN_IS_ACTIVE, self::COLUMN_STARTS_AT, self::COLUMN_ENDS_AT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'بنر: '.$this->getTitle();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_LINK_TYPE => BannerLinkType::class,
            self::COLUMN_LINK_ID => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
            self::COLUMN_STARTS_AT => 'datetime',
            self::COLUMN_ENDS_AT => 'datetime',
        ];
    }
}
