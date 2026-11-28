<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        {{-- The browser's address bar takes the header's colour (bg-card), so it reads as part of the page. --}}
        <meta name="theme-color" content="#ffffff">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <x-seo::head :title="$title ?? null" :description="$description ?? null" :seo="$seo ?? null" />

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
