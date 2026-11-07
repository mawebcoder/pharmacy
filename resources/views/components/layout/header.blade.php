{{--
    Site header (roadmap B9.1). Mobile 56px with search below, tablet 64px, desktop 72px.
    Below the header row: the category bar and mega-menu (Catalog module, S08). The mini-cart total plugs in with S19.
--}}
{{-- Publishes the header's current height as --header-height for sticky bars below it (e.g. the brand letter bar). --}}
<header
    class="sticky top-0 z-40 border-b border-line-200 bg-white"
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

        <x-layout.search class="hidden flex-1 md:block xl:max-w-xl" />

        <nav class="ms-auto flex items-center gap-1" aria-label="حساب و سبد خرید">
            <a href="/pharmacist" class="hidden h-11 items-center gap-2 rounded-full px-3 text-sm text-ink-900 hover:bg-surface-50 xl:flex">
                <x-ts-icon icon="chat-bubble-left-right" class="size-5 text-primary-700" />
                مشاوره با داروساز
            </a>
            <a href="/account" class="grid size-11 place-items-center rounded-full text-ink-900 hover:bg-surface-50" aria-label="حساب من">
                <x-ts-icon icon="user" class="size-6" />
            </a>
            <a href="/cart" class="flex h-11 items-center gap-2 rounded-full px-3 text-ink-900 hover:bg-surface-50" aria-label="سبد خرید">
                <x-ts-icon icon="shopping-bag" class="size-6" />
                <span class="hidden text-sm xl:inline">سبد خرید</span>
            </a>
        </nav>
    </div>

    <x-catalog::category-nav />

    <div class="border-t border-line-200 px-4 py-2 md:hidden">
        <x-layout.search id="site-search-mobile" />
    </div>
</header>
