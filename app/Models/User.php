<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Laravel's default "web" guard user. Customers get their own model and table in S17.
 */
#[Fillable([self::COLUMN_NAME, self::COLUMN_EMAIL, self::COLUMN_PASSWORD])]
#[Hidden([self::COLUMN_PASSWORD, self::COLUMN_REMEMBER_TOKEN])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME = 'name';

    public const string COLUMN_EMAIL = 'email';

    public const string COLUMN_EMAIL_VERIFIED_AT = 'email_verified_at';

    public const string COLUMN_PASSWORD = 'password';

    public const string COLUMN_REMEMBER_TOKEN = 'remember_token';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

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

    public function getEmail(): string
    {
        return (string) $this->getAttribute(self::COLUMN_EMAIL);
    }

    public function setEmail(string $email): static
    {
        return $this->setAttribute(self::COLUMN_EMAIL, $email);
    }

    public function getEmailVerifiedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_EMAIL_VERIFIED_AT);
    }

    public function setEmailVerifiedAt(?Carbon $emailVerifiedAt): static
    {
        return $this->setAttribute(self::COLUMN_EMAIL_VERIFIED_AT, $emailVerifiedAt);
    }

    /**
     * The password hash.
     */
    public function getPassword(): string
    {
        return (string) $this->getAttribute(self::COLUMN_PASSWORD);
    }

    /**
     * Takes the plain password; the "hashed" cast stores its hash.
     */
    public function setPassword(string $plainPassword): static
    {
        return $this->setAttribute(self::COLUMN_PASSWORD, $plainPassword);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_EMAIL_VERIFIED_AT => 'datetime',
            self::COLUMN_PASSWORD => 'hashed',
        ];
    }
}
