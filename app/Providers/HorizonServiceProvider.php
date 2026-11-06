<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Horizon is for signed-in admins (the "admin" guard) who have set up 2FA, in every environment.
     * The parent class would let anyone in locally.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn (Request $request): bool => Gate::forUser($request->user('admin'))->allows('viewHorizon'));
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?AdminUser $admin = null): bool => $admin instanceof AdminUser
            && $admin->is_active
            && $admin->hasTwoFactorAuthentication());
    }
}
