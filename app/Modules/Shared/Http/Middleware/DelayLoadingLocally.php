<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local demo of every loading state (owner, 2026-11-14): holds back Livewire requests, both pages fetched by
 * wire:navigate (page skeletons) and component updates (search spinner, popular-products skeleton, add-to-cart
 * spinner…), for config('app.local_loading_delay_ms') milliseconds, because local requests answer too fast to see
 * them. Never outside APP_ENV=local, whatever the setting says; 0 turns it off; capped at 5 seconds.
 */
final class DelayLoadingLocally
{
    public function handle(Request $request, Closure $next): Response
    {
        $delay = (int) config('app.local_loading_delay_ms');
        $isLivewireRequest = $request->headers->has('X-Livewire-Navigate') || $request->headers->has('X-Livewire');

        if ($delay > 0 && $isLivewireRequest && app()->isLocal()) {
            usleep(min($delay, 5000) * 1000);
        }

        return $next($request);
    }
}
