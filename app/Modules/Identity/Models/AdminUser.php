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
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * The TOTP secret and recovery codes are stored encrypted by Filament's traits.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
final class AdminUser extends Authenticatable implements FilamentUser, HasActivityLogLabel, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use LogsActivity;
    use Notifiable;

    /**
     * Roles and permissions belong to the "admin" guard, so customers can never hold them.
     */
    protected string $guard_name = 'admin';

    /**
     * Audit trail (roadmap D16): who changed which account. Passwords and 2FA secrets are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return "مدیر: {$this->name}";
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
        return $this->is_active && $this->hasTwoFactorAuthentication() && $this->isSuperAdmin();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Whether the admin has finished setting up an authenticator app (required, roadmap D16).
     */
    public function hasTwoFactorAuthentication(): bool
    {
        return filled($this->getAppAuthenticationSecret());
    }
}
