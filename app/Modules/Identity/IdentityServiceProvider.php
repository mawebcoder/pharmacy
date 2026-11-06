<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Listeners\RecordAdminLogin;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rules\Password;

final class IdentityServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(Login::class, RecordAdminLogin::class);

        // Admin passwords (roadmap D16). Customers sign in with OTP and have no password.
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers()->symbols());
    }
}
