<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\PageFactory;
use App\Modules\Content\Queries\MenuLinks;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A static page (roadmap D3.6, S29) at /pages/{slug}: «درباره ما», «شرایط استفاده», … The body is rich-editor HTML,
 * sanitised again when shown; images in it live on the default filesystem disk. Only active pages are public.
 */
final class Page extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_SLUG = 'slug';

    public const string COLUMN_BODY = 'body';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_SEO_TITLE = 'seo_title';

    public const string COLUMN_SEO_DESCRIPTION = 'seo_description';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    /** Where the rich editor stores page images on the default disk. */
    public const string ATTACHMENTS_DIRECTORY = 'pages';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getTitle(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE);
    }

    public function setTitle(string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE, $title);
    }

    public function getSlug(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SLUG);
    }

    public function setSlug(string $slug): static
    {
        return $this->setAttribute(self::COLUMN_SLUG, $slug);
    }

    public function getBody(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_BODY);

        return $value === null ? null : (string) $value;
    }

    public function setBody(?string $body): static
    {
        return $this->setAttribute(self::COLUMN_BODY, $body);
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
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

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    public function url(): string
    {
        return route('pages.show', $this->getSlug());
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
        return 'صفحه: '.$this->getTitle();
    }

    /**
     * Audit trail: who changed which page (the body is logged as changed, not copied).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_TITLE, self::COLUMN_SLUG, self::COLUMN_IS_ACTIVE, self::COLUMN_SEO_TITLE, self::COLUMN_SEO_DESCRIPTION])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Menus show a page only while it is live, so their cache is cleared when a page changes.
     */
    protected static function booted(): void
    {
        self::saved(fn () => MenuLinks::forget());
        self::deleted(fn () => MenuLinks::forget());
        self::restored(fn () => MenuLinks::forget());
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
