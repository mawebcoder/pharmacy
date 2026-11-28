{{--
    Maintenance (503; owner, 2026-11-28): shown to customers and admins alike while the site is down
    (`php artisan down`), so it reads nothing from the database or the modules, only the site's own CSS and fonts. A
    calm illustration (a capsule with a wrench), a short note, «تا چند دقیقه دیگر» (never an exact time) and
    «تلاش دوباره». The page reloads itself every minute, so a visitor gets back in without doing anything.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#0f766e">
        <meta name="robots" content="noindex">
        <meta http-equiv="refresh" content="60">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <title>در حال به‌روزرسانی | {{ config('app.display_name') }}</title>
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-surface-50 font-sans text-ink-900 antialiased">
        <main class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12 text-center">
            {{-- Soft background glow, in the theme's colours. --}}
            <div class="pointer-events-none absolute inset-x-0 top-1/4 -z-10 mx-auto size-72 rounded-full bg-primary-50 blur-3xl md:size-[28rem]" aria-hidden="true"></div>

            <div class="flex items-center gap-2" aria-hidden="true">
                <span class="grid size-9 place-items-center rounded-card bg-primary-700 text-white">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                </span>
                <span class="text-lg font-bold text-primary-700">{{ config('app.display_name') }}</span>
            </div>

            {{-- A capsule, half brand teal and half coral, with a wrench crossing it; a few floating dots. --}}
            <div class="relative mt-10 select-none" aria-hidden="true">
                <svg viewBox="0 0 220 160" class="w-56 md:w-64" fill="none">
                    <g transform="rotate(-28 110 80)">
                        <rect x="34" y="52" width="152" height="56" rx="28" class="fill-card" />
                        <path d="M110 52h48a28 28 0 0 1 0 56h-48z" class="fill-accent-500" />
                        <path d="M62 52h48v56H62a28 28 0 0 1 0-56z" class="fill-primary-700" />
                        <rect x="34" y="52" width="152" height="56" rx="28" class="stroke-line-300" stroke-width="3" />
                        <path d="M124 64h26a14 14 0 0 1 14 14" class="stroke-white/70" stroke-width="5" stroke-linecap="round" />
                    </g>
                    <g transform="rotate(38 118 86)">
                        <path d="M88 86h66" class="stroke-ink-500" stroke-width="10" stroke-linecap="round" />
                        <path d="M154 72a16 16 0 1 0 0 28l-6-8v-12z" class="fill-ink-500" />
                        <circle cx="86" cy="86" r="4" class="fill-card" />
                    </g>
                </svg>
                <span class="absolute -start-4 top-8 size-3 rounded-full bg-accent-500/70"></span>
                <span class="absolute end-2 -top-2 size-2 rounded-full bg-primary-700/60"></span>
                <span class="absolute -end-6 bottom-6 size-4 rounded-full bg-primary-50 ring-2 ring-primary-700/30"></span>
            </div>

            <h1 class="mt-8 text-2xl font-bold text-ink-900 md:text-3xl">در حال به‌روزرسانی هستیم</h1>
            <p class="mt-3 max-w-md leading-8 text-ink-500">
                {{ config('app.display_name') }} برای چند لحظه در دسترس نیست تا تغییرات تازه را روی سایت بگذاریم. سبد خرید و سفارش‌های شما سر جایشان می‌مانند.
            </p>

            <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-primary-50 px-4 py-2 text-sm font-medium text-primary-800">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                تا چند دقیقه دیگر برمی‌گردیم
            </p>

            <a href="{{ url()->current() }}" class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-card bg-primary-700 px-8 font-bold text-white shadow-card hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" /></svg>
                تلاش دوباره
            </a>
            <p class="mt-4 text-xs text-ink-500">این صفحه هر دقیقه خودش دوباره امتحان می‌کند.</p>
        </main>
    </body>
</html>
