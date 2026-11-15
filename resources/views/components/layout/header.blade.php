{{--
    Site header (roadmap B9.1). Mobile 56px with search below, tablet 64px, desktop 72px.
    Below the header row: the category bar and mega-menu (Catalog module, S08). The mini-cart total plugs in with S19.
--}}
{{-- Publishes the header's current height as --header-height for sticky bars below it (e.g. the brand letter bar).
     While the full-screen search is open (phones, tablets) the header, which holds it, rises above the bottom nav. --}}
<header
    class="sticky top-0 z-40 border-b border-line-200 bg-card max-xl:[html[data-search-open]_&]:z-[70]"
    x-data
    x-init="
        const publishHeight = () => document.documentElement.style.setProperty('--header-height', $el.offsetHeight + 'px');
        publishHeight();
        new ResizeObserver(publishHeight).observe($el);
    "
>
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 md:h-16 md:gap-6 md:px-6 xl:h-18 xl:px-8">
        <a href="{{ route('home') }}" class="flex h-11 shrink-0 items-center gap-2 rounded-md focus-visible:outline-2 focus-visible:outline-primary-700">
            <span class="grid size-9 place-items-center rounded-card bg-primary-700 text-white">
                <x-ts-icon icon="plus" class="size-5" />
            </span>
            <span class="text-lg font-bold text-primary-700">{{ config('app.display_name') }}</span>
        </a>

        {{-- One search bar per page (owner, 2026-11-15): on /search the header's box holds the query. --}}
        @php
            $searchQuery = request()->routeIs('search') ? (string) request()->query('q', '') : '';
        @endphp
        <livewire:search::instant-search :value="$searchQuery" class="hidden flex-1 md:block xl:max-w-xl" />

        <nav class="ms-auto flex items-center gap-1" aria-label="حساب و سبد خرید">
            <x-ui.pharmacist-chat label="مشاوره با داروساز" class="flex h-11 min-w-11 items-center justify-center gap-2 rounded-full px-2.5 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700 xl:px-3" icon-class="size-6 text-primary-700 xl:size-5" label-class="sr-only xl:not-sr-only" />
            <livewire:identity::theme-toggle />
            @auth('customer')
                {{-- Signed-in customer (S17): their name, the account area (S18) and sign-out; the page dims behind the menu
                     (owner, 2026-11-15). --}}
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                    <x-layout.header-backdrop show="open" close="open = false" />
                    <button
                        type="button"
                        class="flex h-11 items-center gap-2 rounded-full px-2 text-ink-900 hover:bg-surface-50 xl:px-3"
                        aria-label="حساب من"
                        aria-haspopup="menu"
                        x-bind:aria-expanded="open"
                        x-on:click="open = ! open"
                    >
                        <x-ts-icon icon="user-circle" class="size-6 text-primary-700" />
                        <span class="hidden max-w-32 truncate text-sm xl:inline">{{ \App\Modules\Shared\Support\PersianDigits::toPersian(auth('customer')->user()->displayName()) }}</span>
                        <x-ts-icon icon="chevron-down" class="hidden size-4 xl:block" />
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        x-transition.origin.top
                        {{-- A link closes the menu at once; the page shows its own skeleton while it loads (owner, 2026-11-15). --}}
                        x-on:click="$event.target.closest('a[href]') && (open = false)"
                        role="menu"
                        class="absolute end-0 top-full z-[60] mt-2 w-56 rounded-card border border-line-200 bg-card py-2 shadow-lg"
                    >
                        <p class="truncate px-4 pb-2 text-sm font-bold text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian(auth('customer')->user()->displayName()) }}</p>
                        @foreach ([
                            ['حساب من', 'squares-2x2', route('account')],
                            ['سفارش‌ها', 'shopping-bag', route('account.orders')],
                            ['آدرس‌ها', 'map-pin', route('account.addresses')],
                            ['علاقه‌مندی‌ها', 'heart', route('account.wishlist')],
                            ['اطلاعات حساب', 'user', route('account.profile')],
                        ] as [$label, $icon, $href])
                            <a href="{{ $href }}" wire:navigate role="menuitem" class="flex min-h-11 items-center gap-2 px-4 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700">
                                <x-ts-icon :icon="$icon" class="size-5 text-ink-500" />
                                {{ $label }}
                            </a>
                        @endforeach
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line-200 pt-1">
                            @csrf
                            <button type="submit" role="menuitem" class="flex min-h-11 w-full items-center gap-2 px-4 text-sm text-danger-600 hover:bg-surface-50">
                                <x-ts-icon icon="arrow-right-start-on-rectangle" class="size-5" />
                                خروج
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="flex h-11 items-center gap-2 rounded-full px-2 text-ink-900 hover:bg-surface-50 xl:px-3" aria-label="ورود یا ثبت‌نام">
                    <x-ts-icon icon="user" class="size-6" />
                    <span class="hidden text-sm xl:inline">ورود | ثبت‌نام</span>
                </a>
            @endauth
            <livewire:cart::mini-cart />
        </nav>
    </div>

    {{-- Site links beside «دسته‌بندی کالاها» on desktop (owner, 2026-11-15); a page shows only once it is live. --}}
    @php
        $pageLinks = app(\App\Modules\Content\Queries\PageLinks::class);
        $siteLinks = array_values(array_filter([
            ['label' => 'همه کالاها', 'href' => route('products.index'), 'icon' => 'squares-2x2'],
            ['label' => 'برندها', 'href' => route('brands.index'), 'icon' => 'tag'],
            ['label' => 'درباره ما', 'href' => $pageLinks->urlOfSlug('about'), 'icon' => 'information-circle'],
            ['label' => 'تماس با ما', 'href' => Route::has('contact') ? route('contact') : null, 'icon' => 'phone'],
        ], fn (array $link): bool => $link['href'] !== null));
    @endphp
    <x-catalog::category-nav :links="$siteLinks" />

    <div class="border-t border-line-200 px-4 py-2 md:hidden">
        <livewire:search::instant-search input-id="site-search-mobile" :value="$searchQuery" />
    </div>
</header>
