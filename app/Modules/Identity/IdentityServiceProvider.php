<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Listeners\LogRoleAndPermissionChanges;
use App\Modules\Identity\Listeners\RecordAdminLogin;
use App\Modules\Identity\Policies\RolePolicy;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

final class IdentityServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(Login::class, RecordAdminLogin::class);
        Event::listen([
            RoleAttachedEvent::class,
            RoleDetachedEvent::class,
            PermissionAttachedEvent::class,
            PermissionDetachedEvent::class,
        ], LogRoleAndPermissionChanges::class);

        Gate::policy(Role::class, RolePolicy::class);

        // Built-in roles are referenced in code: never delete them, whatever the UI does.
        Role::deleting(fn (Role $role): bool => ! AdminRole::isBuiltIn($role->name));

        // Admin passwords (roadmap D16). Customers sign in with OTP and have no password.
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers()->symbols());
    }
}
