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

        <livewire:search::instant-search class="hidden flex-1 md:block xl:max-w-xl" />

        <nav class="ms-auto flex items-center gap-1" aria-label="حساب و سبد خرید">
            <a href="/pharmacist" class="hidden h-11 items-center gap-2 rounded-full px-3 text-sm text-ink-900 hover:bg-surface-50 xl:flex">
                <x-ts-icon icon="chat-bubble-left-right" class="size-5 text-primary-700" />
                مشاوره با داروساز
            </a>
            @auth('customer')
                {{-- Signed-in customer (S17): their name and sign-out; the account area comes with S18. --}}
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
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
                        role="menu"
                        class="absolute end-0 top-full z-50 mt-2 w-56 rounded-card border border-line-200 bg-white py-2 shadow-lg"
                    >
                        <p class="truncate px-4 pb-2 text-sm font-bold text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian(auth('customer')->user()->displayName()) }}</p>
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
            <a href="/cart" class="flex h-11 items-center gap-2 rounded-full px-3 text-ink-900 hover:bg-surface-50" aria-label="سبد خرید">
                <x-ts-icon icon="shopping-bag" class="size-6" />
                <span class="hidden text-sm xl:inline">سبد خرید</span>
            </a>
        </nav>
    </div>

    <x-catalog::category-nav />

    <div class="border-t border-line-200 px-4 py-2 md:hidden">
        <livewire:search::instant-search input-id="site-search-mobile" />
    </div>
</header>
