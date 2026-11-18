<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Modules\Media\Concerns\InteractsWithImagePresets;
use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Settings\Database\Factories\PharmacistFactory;
use App\Modules\Settings\Queries\Pharmacists;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A pharmacist of the store (roadmap D3.6, S30): name, medical council number («شماره نظام پزشکی»), photo and a short
 * bio. One is the pharmacist in charge («داروساز مسئول»), named on /pharmacist and on every product page.
 */
final class Pharmacist extends Model implements HasActivityLogLabel, HasImagePresets
{
    /** @use HasFactory<PharmacistFactory> */
    use HasFactory;

    use InteractsWithImagePresets;
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_FULL_NAME = 'full_name';

    public const string COLUMN_LICENSE_NUMBER = 'license_number';

    public const string COLUMN_BIO = 'bio';

    public const string COLUMN_IS_RESPONSIBLE = 'is_responsible';

    public const string COLUMN_SORT = 'sort';

    public const string IMAGE_PHOTO = 'photo';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getFullName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_FULL_NAME);
    }

    public function setFullName(string $fullName): static
    {
        return $this->setAttribute(self::COLUMN_FULL_NAME, $fullName);
    }

    public function getLicenseNumber(): string
    {
        return (string) $this->getAttribute(self::COLUMN_LICENSE_NUMBER);
    }

    public function setLicenseNumber(string $licenseNumber): static
    {
        return $this->setAttribute(self::COLUMN_LICENSE_NUMBER, $licenseNumber);
    }

    public function getBio(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_BIO);

        return $value === null ? null : (string) $value;
    }

    public function setBio(?string $bio): static
    {
        return $this->setAttribute(self::COLUMN_BIO, $bio);
    }

    public function isResponsible(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_RESPONSIBLE);
    }

    public function setIsResponsible(bool $isResponsible): static
    {
        return $this->setAttribute(self::COLUMN_IS_RESPONSIBLE, $isResponsible);
    }

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
    }

    public function getPhoto(): ?StoredImage
    {
        return $this->image(self::IMAGE_PHOTO);
    }

    /**
     * @return array<string, string>
     */
    public function imagePresets(): array
    {
        return [self::IMAGE_PHOTO => 'pharmacist_photo'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeResponsible(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_RESPONSIBLE, true);
    }

    public function activityLogLabel(): string
    {
        return 'داروساز: '.$this->getFullName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly([self::COLUMN_FULL_NAME, self::COLUMN_LICENSE_NUMBER, self::COLUMN_IS_RESPONSIBLE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        self::saved(fn () => Pharmacists::forget());
        self::deleted(fn () => Pharmacists::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_IS_RESPONSIBLE => 'boolean',
        ];
    }
}
