<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Models\AdminUser;
use App\Modules\System\Support\QueuedTelescopeRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Laravel\Telescope\Watchers;

/**
 * Telescope at /telescope, on unless TELESCOPE_ENABLED=false. Locally it records everything and writes it at once.
 * Elsewhere (the 2 GB production server) only the error watchers run, it keeps only exceptions, error logs, failed
 * requests and jobs, requests slower than 2 seconds and slow queries, hides secrets, writes them through a queued job
 * (QueuedTelescopeRepository) and drops entries older than 72 hours every night. Locally a queue would not work: the
 * batches are megabytes, and Horizon's own Redis calls would be recorded and queued again without end. Only active admins with exactly the
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

    /** Requests slower than this are kept outside local (owner, 2026-11-27). */
    public const int SLOW_REQUEST_MS = 2000;

    public function register(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        $this->app->singleton(EntriesRepository::class, fn (): QueuedTelescopeRepository => new QueuedTelescopeRepository(
            (string) config('telescope.storage.database.connection'),
            (int) config('telescope.storage.database.chunk'),
        ));

        /** @var array<string, mixed> $watchers */
        $watchers = config('telescope.watchers', []);
        config(['telescope.watchers' => array_intersect_key($watchers, array_flip(self::ERROR_WATCHERS))]);

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password', 'code']);
        Telescope::hideRequestHeaders(['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token']);
        Telescope::filter(fn (IncomingEntry $entry): bool => self::isWorthKeeping($entry));
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
     * What is kept outside local: exceptions, error logs, failed or slow requests, failed jobs, slow queries and
     * monitored tags.
     */
    public static function isWorthKeeping(IncomingEntry $entry): bool
    {
        return $entry->isReportableException()
            || $entry->isLog()
            || $entry->isFailedRequest()
            || ($entry->isRequest() && (int) ($entry->content['duration'] ?? 0) > self::SLOW_REQUEST_MS)
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
