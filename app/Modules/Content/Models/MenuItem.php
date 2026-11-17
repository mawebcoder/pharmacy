<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\MenuItemFactory;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Queries\LinkUrls;
use App\Modules\Content\Queries\MenuLinks;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One link of a menu (roadmap D3.6, S34; owner 2026-11-17): a title, an optional icon, and a target picked like a
 * banner's, so it follows slug changes; an item whose target is gone or hidden is left out of the menu.
 */
final class MenuItem extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_MENU_ID = 'menu_id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_ICON = 'icon';

    public const string COLUMN_LINK_TYPE = 'link_type';

    public const string COLUMN_LINK_ID = 'link_id';

    public const string COLUMN_EXTERNAL_URL = 'external_url';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getMenuId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_MENU_ID);
    }

    public function setMenuId(int $menuId): static
    {
        return $this->setAttribute(self::COLUMN_MENU_ID, $menuId);
    }

    public function getTitle(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE);
    }

    public function setTitle(string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE, $title);
    }

    public function getIcon(): ?MenuIcon
    {
        return $this->getAttribute(self::COLUMN_ICON);
    }

    public function setIcon(?MenuIcon $icon): static
    {
        return $this->setAttribute(self::COLUMN_ICON, $icon);
    }

    public function getLinkType(): LinkType
    {
        return $this->getAttribute(self::COLUMN_LINK_TYPE);
    }

    public function setLinkType(LinkType $type): static
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

    /**
     * Where the item leads now, or null when its target is gone or hidden (the item is then left out).
     */
    public function linkUrl(): ?string
    {
        return app(LinkUrls::class)->url($this->getLinkType(), $this->getLinkId(), $this->getExternalUrl());
    }

    public function opensInNewTab(): bool
    {
        return $this->getLinkType() === LinkType::External;
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
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
        return 'پیوند منو: '.$this->getTitle();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_TITLE, self::COLUMN_ICON, self::COLUMN_LINK_TYPE, self::COLUMN_LINK_ID, self::COLUMN_EXTERNAL_URL, self::COLUMN_IS_ACTIVE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        self::saved(fn () => MenuLinks::forget());
        self::deleted(fn () => MenuLinks::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_ICON => MenuIcon::class,
            self::COLUMN_LINK_TYPE => LinkType::class,
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
