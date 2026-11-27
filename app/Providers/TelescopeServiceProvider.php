<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Models\AdminUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Laravel\Telescope\Watchers;

/**
 * Telescope at /telescope, off unless TELESCOPE_ENABLED=true. Locally it records everything. Elsewhere (the 2 GB
 * production server) only the error watchers run, it keeps only exceptions, error logs, failed requests and jobs and
 * slow queries, hides secrets, and drops entries older than 72 hours every night. Only active admins with exactly the
 * super_admin role and 2FA (the "admin" guard) may open it, in every environment.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /** Watchers that run outside local: the ones that can see an error or a slow query. */
    public const array ERROR_WATCHERS = [
        Watchers\ExceptionWatcher::class,
        Watchers\JobWatcher::class,
        Watchers\LogWatcher::class,
        Watchers\QueryWatcher::class,
        Watchers\RequestWatcher::class,
    ];

    public const int PRUNE_AFTER_HOURS = 72;

    public function register(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        /** @var array<string, mixed> $watchers */
        $watchers = config('telescope.watchers', []);
        config(['telescope.watchers' => array_intersect_key($watchers, array_flip(self::ERROR_WATCHERS))]);

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password', 'code']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token']);
        Telescope::filter(fn (IncomingEntry $entry): bool => self::isError($entry));
    }

    public function boot(): void
    {
        parent::boot();

        if (! config('telescope.enabled')) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('telescope:prune', ['--hours' => self::PRUNE_AFTER_HOURS])
                ->dailyAt('04:30')
                ->timezone((string) config('app.display_timezone'))
                ->onOneServer();
        });
    }

    /**
     * What is kept outside local: exceptions, error logs, failed requests and jobs, slow queries and monitored tags.
     */
    public static function isError(IncomingEntry $entry): bool
    {
        return $entry->isReportableException()
            || $entry->isLog()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->isSlowQuery()
            || $entry->hasMonitoredTag();
    }

    /**
     * The parent class would let anyone in locally.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function (Request $request): bool {
            $admin = $request->user('admin');

            return $admin instanceof AdminUser && $admin->canViewTelescope();
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?AdminUser $admin = null): bool => $admin instanceof AdminUser && $admin->canViewTelescope());
    }
}
