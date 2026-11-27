<?php

declare(strict_types=1);

namespace App\Modules\Seo\Models;

use App\Modules\Seo\Database\Factories\SeoRedirectFactory;
use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An old address sent on to a new one (roadmap D3.6, S38). Paths start with «/», without the domain or query string;
 * the query string is passed on. Read through the cached map of RedirectOldPaths.
 */
final class SeoRedirect extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<SeoRedirectFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_FROM_PATH = 'from_path';

    public const string COLUMN_TO_PATH = 'to_path';

    public const string COLUMN_STATUS_CODE = 'status_code';

    public const string COLUMN_SOURCE = 'source';

    public const string COLUMN_HITS = 'hits';

    public const string COLUMN_LAST_HIT_AT = 'last_hit_at';

    public const array STATUS_CODES = [301, 302];

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getFromPath(): string
    {
        return (string) $this->getAttribute(self::COLUMN_FROM_PATH);
    }

    public function setFromPath(string $path): static
    {
        return $this->setAttribute(self::COLUMN_FROM_PATH, $path);
    }

    public function getToPath(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TO_PATH);
    }

    public function setToPath(string $path): static
    {
        return $this->setAttribute(self::COLUMN_TO_PATH, $path);
    }

    public function getStatusCode(): int
    {
        return (int) $this->getAttribute(self::COLUMN_STATUS_CODE);
    }

    public function setStatusCode(int $code): static
    {
        return $this->setAttribute(self::COLUMN_STATUS_CODE, $code);
    }

    public function getSource(): RedirectSource
    {
        return $this->getAttribute(self::COLUMN_SOURCE);
    }

    public function setSource(RedirectSource $source): static
    {
        return $this->setAttribute(self::COLUMN_SOURCE, $source);
    }

    public function getHits(): int
    {
        return (int) $this->getAttribute(self::COLUMN_HITS);
    }

    public function getLastHitAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_LAST_HIT_AT);
    }

    public function activityLogLabel(): string
    {
        return 'ریدایرکت: '.$this->getFromPath();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('seo')
            ->logOnly([self::COLUMN_FROM_PATH, self::COLUMN_TO_PATH, self::COLUMN_STATUS_CODE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        // The middleware reads a cached map of every redirect.
        self::saved(fn () => cache()->forget(self::CACHE_KEY));
        self::deleted(fn () => cache()->forget(self::CACHE_KEY));
    }

    public const string CACHE_KEY = 'seo:redirects';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS_CODE => 'integer',
            self::COLUMN_SOURCE => RedirectSource::class,
            self::COLUMN_HITS => 'integer',
            self::COLUMN_LAST_HIT_AT => 'datetime',
        ];
    }
}
