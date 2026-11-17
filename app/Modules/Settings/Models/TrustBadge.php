<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Settings\Database\Factories\TrustBadgeFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A trust badge in the footer (roadmap D3.6, S35): the embed code e-Namad or Samandehi hands out (cleaned by
 * TrustBadgeEmbed), or an uploaded image such as a licence, with an optional link.
 */
final class TrustBadge extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<TrustBadgeFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_EMBED_CODE = 'embed_code';

    public const string COLUMN_LINK_URL = 'link_url';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string IMAGE = 'image';

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

    public function getEmbedCode(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_EMBED_CODE);

        return $value === null ? null : (string) $value;
    }

    public function setEmbedCode(?string $embedCode): static
    {
        return $this->setAttribute(self::COLUMN_EMBED_CODE, $embedCode);
    }

    public function getLinkUrl(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_LINK_URL);

        return $value === null ? null : (string) $value;
    }

    public function setLinkUrl(?string $linkUrl): static
    {
        return $this->setAttribute(self::COLUMN_LINK_URL, $linkUrl);
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

    public function getImage(): ?StoredImage
    {
        return $this->image(self::IMAGE);
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [self::IMAGE => 'trust_badge'];
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
        return 'نماد اعتماد: '.$this->getTitle();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly([self::COLUMN_TITLE, self::COLUMN_EMBED_CODE, self::COLUMN_LINK_URL, self::COLUMN_IS_ACTIVE])
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
