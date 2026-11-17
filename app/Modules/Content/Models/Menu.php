<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\MenuFactory;
use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Queries\MenuLinks;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A site menu (roadmap D3.6, S34; owner 2026-11-17): one per location (header, the three footer columns, the phone
 * and tablet menu), with an optional heading and its items in order.
 */
final class Menu extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_LOCATION = 'location';

    public const string COLUMN_TITLE = 'title';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getLocation(): MenuLocation
    {
        return $this->getAttribute(self::COLUMN_LOCATION);
    }

    public function setLocation(MenuLocation $location): static
    {
        return $this->setAttribute(self::COLUMN_LOCATION, $location);
    }

    public function getTitle(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_TITLE);

        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE, $title);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy(MenuItem::COLUMN_SORT)->orderBy(MenuItem::COLUMN_ID);
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function activityLogLabel(): string
    {
        return 'منو: '.$this->getLocation()->label();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_TITLE])
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
            self::COLUMN_LOCATION => MenuLocation::class,
        ];
    }
}
