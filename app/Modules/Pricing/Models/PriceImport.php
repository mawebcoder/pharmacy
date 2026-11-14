<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Database\Factories\PriceImportFactory;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One supplier price upload from Excel (roadmap D13.9, S16): the file, where it is (PriceImportStatus), its row
 * counts and who uploaded it. Its checked rows are PriceImportRow.
 */
final class PriceImport extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<PriceImportFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_FILE_PATH = 'file_path';

    public const string COLUMN_ORIGINAL_NAME = 'original_name';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_ROWS_TOTAL = 'rows_total';

    public const string COLUMN_ROWS_MATCHED = 'rows_matched';

    public const string COLUMN_ROWS_UPDATED = 'rows_updated';

    public const string COLUMN_ROWS_FAILED = 'rows_failed';

    public const string COLUMN_ERROR = 'error';

    public const string COLUMN_ADMIN_ID = 'admin_id';

    public const string COLUMN_APPLIED_AT = 'applied_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    /**
     * Path on the local (private) disk.
     */
    public function getFilePath(): string
    {
        return (string) $this->getAttribute(self::COLUMN_FILE_PATH);
    }

    public function setFilePath(string $filePath): static
    {
        return $this->setAttribute(self::COLUMN_FILE_PATH, $filePath);
    }

    public function getOriginalName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_ORIGINAL_NAME);
    }

    public function setOriginalName(string $originalName): static
    {
        return $this->setAttribute(self::COLUMN_ORIGINAL_NAME, $originalName);
    }

    public function getStatus(): PriceImportStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(PriceImportStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getRowsTotal(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ROWS_TOTAL);
    }

    public function setRowsTotal(int $rows): static
    {
        return $this->setAttribute(self::COLUMN_ROWS_TOTAL, $rows);
    }

    public function getRowsMatched(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ROWS_MATCHED);
    }

    public function setRowsMatched(int $rows): static
    {
        return $this->setAttribute(self::COLUMN_ROWS_MATCHED, $rows);
    }

    public function getRowsUpdated(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ROWS_UPDATED);
    }

    public function setRowsUpdated(int $rows): static
    {
        return $this->setAttribute(self::COLUMN_ROWS_UPDATED, $rows);
    }

    public function getRowsFailed(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ROWS_FAILED);
    }

    public function setRowsFailed(int $rows): static
    {
        return $this->setAttribute(self::COLUMN_ROWS_FAILED, $rows);
    }

    /**
     * Why the file could not be read at all (row problems are on the rows).
     */
    public function getError(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_ERROR);

        return $value === null ? null : (string) $value;
    }

    public function setError(?string $error): static
    {
        return $this->setAttribute(self::COLUMN_ERROR, $error);
    }

    public function getAdminId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_ADMIN_ID);

        return $value === null ? null : (int) $value;
    }

    public function setAdminId(?int $adminId): static
    {
        return $this->setAttribute(self::COLUMN_ADMIN_ID, $adminId);
    }

    public function getAppliedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_APPLIED_AT);
    }

    public function setAppliedAt(?Carbon $appliedAt): static
    {
        return $this->setAttribute(self::COLUMN_APPLIED_AT, $appliedAt);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return HasMany<PriceImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(PriceImportRow::class);
    }

    public function activityLogLabel(): string
    {
        return 'ورود قیمت از اکسل: '.$this->getOriginalName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly([self::COLUMN_ORIGINAL_NAME, self::COLUMN_STATUS, self::COLUMN_ROWS_TOTAL, self::COLUMN_ROWS_UPDATED, self::COLUMN_ROWS_FAILED])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => PriceImportStatus::class,
            self::COLUMN_ROWS_TOTAL => 'integer',
            self::COLUMN_ROWS_MATCHED => 'integer',
            self::COLUMN_ROWS_UPDATED => 'integer',
            self::COLUMN_ROWS_FAILED => 'integer',
            self::COLUMN_ADMIN_ID => 'integer',
            self::COLUMN_APPLIED_AT => 'datetime',
        ];
    }
}
