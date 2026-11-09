<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Database\Factories\ProviderFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A supplier we buy from («تأمین‌کننده», roadmap D3.2, S14), e.g. Sina's distribution company. What it charges per
 * SKU is in provider_prices; an inactive supplier's prices are ignored.
 */
final class Provider extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME = 'name';

    public const string COLUMN_CONTACT_NAME = 'contact_name';

    public const string COLUMN_PHONE = 'phone';

    public const string COLUMN_ADDRESS = 'address';

    public const string COLUMN_CITY_ID = 'city_id';

    public const string COLUMN_PAYMENT_TERMS = 'payment_terms';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_NOTES = 'notes';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME);
    }

    public function setName(string $name): static
    {
        return $this->setAttribute(self::COLUMN_NAME, $name);
    }

    public function getContactName(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_CONTACT_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setContactName(?string $contactName): static
    {
        return $this->setAttribute(self::COLUMN_CONTACT_NAME, $contactName);
    }

    public function getPhone(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PHONE);

        return $value === null ? null : (string) $value;
    }

    public function setPhone(?string $phone): static
    {
        return $this->setAttribute(self::COLUMN_PHONE, $phone);
    }

    public function getAddress(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_ADDRESS);

        return $value === null ? null : (string) $value;
    }

    public function setAddress(?string $address): static
    {
        return $this->setAttribute(self::COLUMN_ADDRESS, $address);
    }

    public function getCityId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CITY_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCityId(?int $cityId): static
    {
        return $this->setAttribute(self::COLUMN_CITY_ID, $cityId);
    }

    public function getPaymentTerms(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PAYMENT_TERMS);

        return $value === null ? null : (string) $value;
    }

    public function setPaymentTerms(?string $paymentTerms): static
    {
        return $this->setAttribute(self::COLUMN_PAYMENT_TERMS, $paymentTerms);
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

    public function getNotes(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NOTES);

        return $value === null ? null : (string) $value;
    }

    public function setNotes(?string $notes): static
    {
        return $this->setAttribute(self::COLUMN_NOTES, $notes);
    }

    /**
     * @return HasMany<ProviderPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProviderPrice::class);
    }

    /**
     * @param  Builder<Provider>  $query
     * @return Builder<Provider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    public function activityLogLabel(): string
    {
        return 'تأمین‌کننده: '.$this->getName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pricing')
            ->logOnly([
                self::COLUMN_NAME, self::COLUMN_CONTACT_NAME, self::COLUMN_PHONE, self::COLUMN_ADDRESS,
                self::COLUMN_CITY_ID, self::COLUMN_PAYMENT_TERMS, self::COLUMN_IS_ACTIVE, self::COLUMN_NOTES,
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_CITY_ID => 'integer',
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
