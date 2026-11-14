<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\CustomerFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A storefront customer (roadmap D3.3), signed in with a one-time SMS code on the «customer» guard (S17). The mobile
 * («09xxxxxxxxx») is the identity; the national id is stored encrypted. Blocked customers cannot sign in.
 */
#[Hidden([self::COLUMN_NATIONAL_ID, 'remember_token'])]
final class Customer extends Authenticatable implements HasActivityLogLabel
{
    use HasApiTokens;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_MOBILE = 'mobile';

    public const string COLUMN_FIRST_NAME = 'first_name';

    public const string COLUMN_LAST_NAME = 'last_name';

    public const string COLUMN_NATIONAL_ID = 'national_id';

    public const string COLUMN_EMAIL = 'email';

    public const string COLUMN_BIRTH_DATE = 'birth_date';

    public const string COLUMN_GENDER = 'gender';

    public const string COLUMN_IS_BLOCKED = 'is_blocked';

    public const string COLUMN_LAST_LOGIN_AT = 'last_login_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getMobile(): string
    {
        return (string) $this->getAttribute(self::COLUMN_MOBILE);
    }

    public function setMobile(string $mobile): static
    {
        return $this->setAttribute(self::COLUMN_MOBILE, $mobile);
    }

    public function getFirstName(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_FIRST_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setFirstName(?string $firstName): static
    {
        return $this->setAttribute(self::COLUMN_FIRST_NAME, $firstName);
    }

    public function getLastName(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_LAST_NAME);

        return $value === null ? null : (string) $value;
    }

    public function setLastName(?string $lastName): static
    {
        return $this->setAttribute(self::COLUMN_LAST_NAME, $lastName);
    }

    /**
     * «سارا رضایی», or the mobile when no name is given yet.
     */
    public function displayName(): string
    {
        $name = trim(($this->getFirstName() ?? '').' '.($this->getLastName() ?? ''));

        return $name === '' ? $this->getMobile() : $name;
    }

    public function getNationalId(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_NATIONAL_ID);

        return $value === null ? null : (string) $value;
    }

    public function setNationalId(?string $nationalId): static
    {
        return $this->setAttribute(self::COLUMN_NATIONAL_ID, $nationalId);
    }

    public function getEmail(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_EMAIL);

        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $email): static
    {
        return $this->setAttribute(self::COLUMN_EMAIL, $email);
    }

    public function getBirthDate(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_BIRTH_DATE);
    }

    public function setBirthDate(?Carbon $birthDate): static
    {
        return $this->setAttribute(self::COLUMN_BIRTH_DATE, $birthDate);
    }

    public function getGender(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_GENDER);

        return $value === null ? null : (string) $value;
    }

    public function setGender(?string $gender): static
    {
        return $this->setAttribute(self::COLUMN_GENDER, $gender);
    }

    public function isBlocked(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_BLOCKED);
    }

    public function getIsBlocked(): bool
    {
        return $this->isBlocked();
    }

    public function setIsBlocked(bool $isBlocked): static
    {
        return $this->setAttribute(self::COLUMN_IS_BLOCKED, $isBlocked);
    }

    public function getLastLoginAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_LAST_LOGIN_AT);
    }

    public function setLastLoginAt(?Carbon $lastLoginAt): static
    {
        return $this->setAttribute(self::COLUMN_LAST_LOGIN_AT, $lastLoginAt);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function activityLogLabel(): string
    {
        return 'مشتری: '.$this->displayName();
    }

    /**
     * Admin-relevant changes only; sign-ins (last_login_at) are not audited.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('customer')
            ->logOnly([self::COLUMN_MOBILE, self::COLUMN_FIRST_NAME, self::COLUMN_LAST_NAME, self::COLUMN_EMAIL, self::COLUMN_IS_BLOCKED])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_NATIONAL_ID => 'encrypted',
            self::COLUMN_BIRTH_DATE => 'date',
            self::COLUMN_IS_BLOCKED => 'boolean',
            self::COLUMN_LAST_LOGIN_AT => 'datetime',
        ];
    }
}
