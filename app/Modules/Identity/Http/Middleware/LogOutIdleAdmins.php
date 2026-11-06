<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs an admin out after config('auth.admin_idle_timeout') minutes without a request (roadmap D16).
 */
final class LogOutIdleAdmins
{
    public const string SESSION_KEY = 'admin.last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth()->guard('admin');

        if ($guard->check()) {
            $lastActivityAt = $request->session()->get(self::SESSION_KEY);
            $timeoutSeconds = (int) config('auth.admin_idle_timeout') * 60;

            if (is_int($lastActivityAt) && now()->getTimestamp() - $lastActivityAt > $timeoutSeconds) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('filament.admin.auth.login');
            }

            $request->session()->put(self::SESSION_KEY, now()->getTimestamp());
        }

        return $next($request);
    }
}
