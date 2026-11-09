<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\HomeWidgetFactory;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One block of the home page («چیدمان صفحه اصلی», roadmap D3.6, S13b): a slider, a collection rail, brands,
 * categories, tags or the trust block, in the admin's order, with an optional schedule.
 */
final class HomeWidget extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<HomeWidgetFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_TITLE_FA = 'title_fa';

    public const string COLUMN_SETTINGS = 'settings';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_STARTS_AT = 'starts_at';

    public const string COLUMN_ENDS_AT = 'ends_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getType(): HomeWidgetType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(HomeWidgetType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    /**
     * The heading shown above the widget; null shows none (or the collection's name for a collection rail).
     */
    public function getTitleFa(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_TITLE_FA);

        return $value === null ? null : (string) $value;
    }

    public function setTitleFa(?string $title): static
    {
        return $this->setAttribute(self::COLUMN_TITLE_FA, $title);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return (array) ($this->getAttribute(self::COLUMN_SETTINGS) ?? []);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function setSettings(array $settings): static
    {
        return $this->setAttribute(self::COLUMN_SETTINGS, $settings);
    }

    public function getCollectionId(): ?int
    {
        $value = $this->getSettings()['collection_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * The chosen brand, category or tag ids, in order.
     *
     * @return list<int>
     */
    public function getItemIds(): array
    {
        $key = $this->getType()->itemsKey();

        return $key === null ? [] : array_values(array_map(intval(...), (array) ($this->getSettings()[$key] ?? [])));
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
     * Active and inside its schedule now.
     */
    public function isLive(): bool
    {
        return $this->isActive()
            && (! $this->getStartsAt() instanceof Carbon || $this->getStartsAt()->isPast())
            && (! $this->getEndsAt() instanceof Carbon || $this->getEndsAt()->isFuture());
    }

    /**
     * Widgets the home page shows now, in the admin's order.
     *
     * @param  Builder<HomeWidget>  $query
     * @return Builder<HomeWidget>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true)
            ->where(fn (Builder $starts) => $starts->whereNull(self::COLUMN_STARTS_AT)->orWhere(self::COLUMN_STARTS_AT, '<=', now()))
            ->where(fn (Builder $ends) => $ends->whereNull(self::COLUMN_ENDS_AT)->orWhere(self::COLUMN_ENDS_AT, '>', now()))
            ->orderBy(self::COLUMN_SORT)
            ->orderBy(self::COLUMN_ID);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_TYPE, self::COLUMN_TITLE_FA, self::COLUMN_SETTINGS, self::COLUMN_SORT, self::COLUMN_IS_ACTIVE, self::COLUMN_STARTS_AT, self::COLUMN_ENDS_AT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'ویجت صفحه اصلی: '.($this->getTitleFa() ?? $this->getType()->label());
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_TYPE => HomeWidgetType::class,
            self::COLUMN_SETTINGS => 'array',
            self::COLUMN_SORT => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
            self::COLUMN_STARTS_AT => 'datetime',
            self::COLUMN_ENDS_AT => 'datetime',
        ];
    }
}
