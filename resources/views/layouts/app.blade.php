<!DOCTYPE html>
@php($theme = \App\Modules\Identity\Support\StorefrontTheme::current()->value)
<html lang="fa" dir="rtl" data-theme="{{ $theme }}"@if ($theme === 'dark') class="dark"@endif>
    <head>
        <meta charset="utf-8">
        {{-- Applies the customer's theme before the page paints (system follows the device) and when «اطلاعات حساب» changes it. --}}
        <script>
            (() => {
                const root = document.documentElement;
                const media = window.matchMedia('(prefers-color-scheme: dark)');
                const apply = () => root.classList.toggle('dark', root.dataset.theme === 'dark' || (root.dataset.theme === 'system' && media.matches));
                apply();
                media.addEventListener('change', apply);
                window.addEventListener('theme-changed', (event) => { root.dataset.theme = event.detail.theme; apply(); });
                // wire:navigate copies the server's <html> attributes, which cannot know the device setting.
                document.addEventListener('livewire:navigating', (event) => event.detail?.onSwap?.(apply));
                document.addEventListener('livewire:navigated', apply);
            })();
        </script>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#0f766e">

        <title>{{ filled($title ?? null) ? $title.' | '.config('app.display_name') : config('app.display_name') }}</title>
        @if (filled($description ?? null))
            <meta name="description" content="{{ $description }}">
        @endif

        @fonts
        <tallstackui:script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-surface-50 font-sans text-ink-900 antialiased">
        <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-card focus:px-4 focus:py-2 focus:shadow-card">
            پرش به محتوای اصلی
        </a>

        <x-layout.page-loading />
        <x-ui.confirm-dialog />
        <x-ts-toast />
        <x-ts-dialog />
        <livewire:catalog::wishlist-toggle />
        <livewire:cart::cart-state />

        <x-layout.announcement />
        <x-layout.header />

        <main id="content" class="mx-auto max-w-7xl px-4 py-6 md:px-6 xl:px-8">
            {{ $slot }}
        </main>

        {{-- Keeps the end of the page clear of the mobile bottom navigation (64px + safe area). On phones the cart and the
             checkout steps have no footer, so nothing leads away from the purchase (owner, 2026-11-15). --}}
        <div class="pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0">
            <div @class(['hidden md:block' => request()->routeIs('cart', 'checkout.address', 'checkout')])>
                <x-layout.footer />
            </div>
        </div>

        <x-layout.bottom-nav />
        <x-layout.mobile-menu />

        @livewireScripts
    </body>
</html>
