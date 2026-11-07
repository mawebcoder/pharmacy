<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\AdminUserFactory;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff member who signs in to the Filament admin panel with the "admin" guard (roadmap D16).
 * The TOTP secret and recovery codes are stored encrypted and accessed through Filament's traits
 * (getAppAuthenticationSecret(), saveAppAuthenticationSecret(), …); the remember token through Laravel's.
 */
#[Hidden([self::COLUMN_PASSWORD, self::COLUMN_REMEMBER_TOKEN])]
final class AdminUser extends Authenticatable implements FilamentUser, HasActivityLogLabel, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use LogsActivity;
    use Notifiable;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_NAME = 'name';

    public const string COLUMN_EMAIL = 'email';

    public const string COLUMN_PASSWORD = 'password';

    public const string COLUMN_APP_AUTHENTICATION_SECRET = 'app_authentication_secret';

    public const string COLUMN_APP_AUTHENTICATION_RECOVERY_CODES = 'app_authentication_recovery_codes';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public const string COLUMN_LAST_LOGIN_AT = 'last_login_at';

    public const string COLUMN_REMEMBER_TOKEN = 'remember_token';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    /**
     * Roles and permissions belong to the "admin" guard, so customers can never hold them.
     */
    protected string $guard_name = 'admin';

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

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    /**
     * Audit trail (roadmap D16): who changed which account. Passwords and 2FA secrets are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly([self::COLUMN_NAME, self::COLUMN_EMAIL, self::COLUMN_IS_ACTIVE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'مدیر: '.$this->getName();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(AdminRole::SuperAdmin->value);
    }

    /**
     * Horizon shows job payloads and can retry or delete jobs: active super admins who have set up 2FA only.
     * Checked directly (not through the Gate) because Shield lets super_admin pass every Gate check.
     */
    public function canViewHorizon(): bool
    {
        return $this->isActive() && $this->hasTwoFactorAuthentication() && $this->isSuperAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive();
    }

    public function getFilamentName(): string
    {
        return $this->getName();
    }

    /**
     * Whether the admin has finished setting up an authenticator app (required, roadmap D16).
     */
    public function hasTwoFactorAuthentication(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PASSWORD => 'hashed',
            self::COLUMN_IS_ACTIVE => 'boolean',
            self::COLUMN_LAST_LOGIN_AT => 'datetime',
        ];
    }
}
