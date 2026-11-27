<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope, the local debugging dashboard at /telescope. It is a dev dependency and AppServiceProvider registers it
 * only when APP_ENV=local, so production (composer install --no-dev) never has it. Like Horizon, it shows request and
 * job payloads, so only active super admins with 2FA (the "admin" guard) may open it.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Local only: record every entry, with nothing hidden.
        Telescope::filter(fn (): bool => true);
    }

    /**
     * The parent class would let anyone in locally.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function (Request $request): bool {
            $admin = $request->user('admin');

            return $admin instanceof AdminUser && $admin->canViewHorizon();
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?AdminUser $admin = null): bool => $admin instanceof AdminUser && $admin->canViewHorizon());
    }
}
