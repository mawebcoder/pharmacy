<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#0f766e">

        <title>{{ filled($title ?? null) ? $title.' | '.config('app.display_name') : config('app.display_name') }}</title>

        @fonts
        <tallstackui:script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-surface-50 font-sans text-ink-900 antialiased">
        <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow-card">
            پرش به محتوای اصلی
        </a>

        <x-ts-toast />
        <x-ts-dialog />

        <x-layout.announcement />
        <x-layout.header />

        <main id="content" class="mx-auto max-w-7xl px-4 py-6 md:px-6 xl:px-8">
            {{ $slot }}
        </main>

        {{-- Keeps the end of the page clear of the mobile bottom navigation (64px + safe area). --}}
        <div class="pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0">
            <x-layout.footer />
        </div>

        <x-layout.bottom-nav />

        @livewireScripts
    </body>
</html>
