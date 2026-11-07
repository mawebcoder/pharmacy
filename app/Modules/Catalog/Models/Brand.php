<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\BrandFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use App\Modules\Shared\Support\Countries;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product brand (roadmap D3.1). Shown on the storefront at /b/{slug} while active.
 */
#[Fillable([
    self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_COUNTRY, self::COLUMN_DESCRIPTION,
    self::COLUMN_IS_ACTIVE, self::COLUMN_SEO_TITLE, self::COLUMN_SEO_DESCRIPTION,
])]
final class Brand extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME_FA = 'name_fa';

    public const string COLUMN_NAME_EN = 'name_en';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_COUNTRY = 'country';

    public const string COLUMN_DESCRIPTION = 'description';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_SEO_TITLE = 'seo_title';

    public const string COLUMN_SEO_DESCRIPTION = 'seo_description';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public const string COLUMN_DELETED_AT = 'deleted_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getNameFa(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME_FA);
    }

    public function setNameFa(string $nameFa): static
    {
        return $this->setAttribute(self::COLUMN_NAME_FA, $nameFa);
    }

    public function getNameEn(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NAME_EN);

        return $value === null ? null : (string) $value;
    }

    public function setNameEn(?string $nameEn): static
    {
        return $this->setAttribute(self::COLUMN_NAME_EN, $nameEn);
    }

    public function getSlug(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SLUG);
    }

    public function setSlug(string $slug): static
    {
        return $this->setAttribute(self::COLUMN_SLUG, $slug);
    }

    /**
     * ISO 3166-1 alpha-2 code, e.g. "DE".
     */
    public function getCountry(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_COUNTRY);

        return $value === null ? null : (string) $value;
    }

    public function setCountry(?string $country): static
    {
        return $this->setAttribute(self::COLUMN_COUNTRY, $country === null ? null : strtoupper($country));
    }

    /**
     * The country's Persian name, e.g. "آلمان".
     */
    public function getCountryName(): ?string
    {
        return Countries::name($this->getCountry());
    }

    public function getDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $description): static
    {
        return $this->setAttribute(self::COLUMN_DESCRIPTION, $description);
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

    public function getSeoTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setSeoTitle(?string $seoTitle): static
    {
        return $this->setAttribute(self::COLUMN_SEO_TITLE, $seoTitle);
    }

    public function getSeoDescription(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_SEO_DESCRIPTION);

        return $value === null ? null : (string) $value;
    }

    public function setSeoDescription(?string $seoDescription): static
    {
        return $this->setAttribute(self::COLUMN_SEO_DESCRIPTION, $seoDescription);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    public function getDeletedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_DELETED_AT);
    }

    /**
     * @param  Builder<Brand>  $query
     * @return Builder<Brand>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    /**
     * Whether any product uses this brand; such a brand cannot be deleted (DeleteBrand).
     * Products arrive in S10, which replaces this with a products() relation check.
     */
    public function hasProducts(): bool
    {
        return false;
    }

    public function getRouteKeyName(): string
    {
        return self::COLUMN_SLUG;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([
                self::COLUMN_NAME_FA, self::COLUMN_NAME_EN, self::COLUMN_SLUG, self::COLUMN_COUNTRY,
                self::COLUMN_DESCRIPTION, self::COLUMN_IS_ACTIVE, self::COLUMN_SEO_TITLE, self::COLUMN_SEO_DESCRIPTION,
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'برند: '.$this->getNameFa();
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
