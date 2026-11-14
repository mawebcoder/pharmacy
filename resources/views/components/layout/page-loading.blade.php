{{--
    While wire:navigate fetches the next page (owner, 2026-11-14): the content area under the header is replaced by a
    skeleton shaped like the page being opened (no blur), until the new page swaps in (which replaces this layer with a
    fresh, hidden one). The shape comes from the destination URL: product page, product list (categories, brands, tags,
    collections, search), cart and checkout, account, text page, home; anything else gets the product list. Plain links
    and ordinary form posts (logout, the search form, the gateway) show it too, until the browser leaves. It fades
    in, so a page that arrives at once only flickers faintly. Screen readers hear «در حال بارگذاری صفحه…». Plain
    Tailwind (animate-pulse), no extra package. Locally, LOCAL_LOADING_DELAY_MS holds requests back so it can be seen
    (DelayLoadingLocally).
--}}
@php
    $bar = 'rounded-full bg-line-200';
    $box = 'rounded-card border border-line-200 bg-card';
@endphp

<div
    x-data="{
        loading: false,
        kind: 'list',
        kindOf(url) {
            const path = new URL(url, location.origin).pathname;
            if (path === '/' || path === '/logout') return 'home';
            if (path.startsWith('/products/')) return 'product';
            if (path.startsWith('/cart') || path.startsWith('/checkout')) return 'cart';
            if (path.startsWith('/account')) return 'account';
            if (path.startsWith('/pages/') || path === '/faq' || path === '/contact') return 'text';
            return 'list';
        },
        start(url) {
            this.kind = this.kindOf(url);
            this.loading = true;
        },
        showForLink(event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[href]');
            if (! link || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('wire:navigate')) return;
            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search)) return;
            this.start(url.href);
        },
        showForForm(event) {
            const form = event.target;
            if (event.defaultPrevented || form.hasAttribute('wire:submit') || form.target === '_blank') return;
            this.start(new URL(form.getAttribute('action') || location.href, location.href).href);
        },
    }"
    x-on:livewire:navigate.document="kind = kindOf($event.detail?.url ?? location.href); loading = true"
    x-on:livewire:navigated.document="loading = false"
    x-on:click.document="showForLink($event)"
    x-on:submit.document="showForForm($event)"
    x-on:pageshow.window="loading = false"
>
    <div
        x-show="loading"
        x-cloak
        x-transition:enter="transition-opacity duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-x-0 top-[var(--header-height)] bottom-0 z-30 overflow-hidden bg-surface-50 [contain:strict]"
        aria-hidden="true"
    >
        <div class="mx-auto max-w-7xl animate-pulse px-4 py-6 motion-reduce:animate-none md:px-6 xl:px-8">
            <div class="h-4 w-40 {{ $bar }}"></div>

            {{-- Home: banner, chips, a row of cards. --}}
            <template x-if="kind === 'home'">
                <div>
                    <div class="mt-4 aspect-[16/7] w-full rounded-card bg-line-200 md:aspect-[16/5]"></div>
                    <div class="mt-6 flex gap-3 overflow-hidden">
                        @foreach (range(1, 6) as $chip)
                            <div class="flex shrink-0 flex-col items-center gap-2">
                                <div class="size-16 rounded-full bg-line-200"></div>
                                <div class="h-3 w-14 {{ $bar }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-8 h-5 w-48 {{ $bar }}"></div>
                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-6">
                        @foreach (range(1, 6) as $card)
                            <div @class([$box, 'overflow-hidden', 'hidden md:block' => $card === 3, 'hidden xl:block' => $card > 3])>
                                <div class="aspect-square bg-line-200"></div>
                                <div class="space-y-2 p-3"><div class="h-3 w-1/3 {{ $bar }}"></div><div class="h-3.5 w-5/6 {{ $bar }}"></div><div class="h-4 w-1/2 {{ $bar }}"></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>

            {{-- Product list: title, filter chips, sidebar on desktop, grid of cards. --}}
            <template x-if="kind === 'list'">
                <div>
                    <div class="mt-4 h-7 w-64 max-w-full {{ $bar }}"></div>
                    <div class="mt-5 flex gap-2 overflow-hidden">
                        @foreach (range(1, 5) as $chip)
                            <div class="h-9 w-24 shrink-0 rounded-full bg-line-200"></div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex gap-6">
                        <div class="hidden w-64 shrink-0 space-y-3 xl:block">
                            <div class="h-64 {{ $box }}"></div>
                            <div class="h-40 {{ $box }}"></div>
                        </div>
                        <div class="grid flex-1 grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-4">
                            @foreach (range(1, 8) as $card)
                                <div @class([$box, 'overflow-hidden', 'hidden md:block' => $card > 4 && $card <= 6, 'hidden xl:block' => $card > 6])>
                                    <div class="aspect-square bg-line-200"></div>
                                    <div class="space-y-2 p-3">
                                        <div class="h-3 w-1/3 {{ $bar }}"></div>
                                        <div class="h-3.5 w-5/6 {{ $bar }}"></div>
                                        <div class="h-3.5 w-2/3 {{ $bar }}"></div>
                                        <div class="flex items-center justify-between pt-2"><div class="h-4 w-20 {{ $bar }}"></div><div class="size-9 rounded-full bg-line-200"></div></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>

            {{-- Product page: gallery, info, buy box (B9.5 layout). --}}
            <template x-if="kind === 'product'">
                <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-12 xl:gap-8">
                    <div class="xl:col-span-5">
                        <div class="aspect-square rounded-card bg-line-200"></div>
                        <div class="mt-3 hidden gap-2 md:flex">
                            @foreach (range(1, 4) as $thumb)
                                <div class="size-16 rounded-md bg-line-200"></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="space-y-3 xl:col-span-4">
                        <div class="h-4 w-24 {{ $bar }}"></div>
                        <div class="h-7 w-5/6 {{ $bar }}"></div>
                        <div class="h-4 w-1/2 {{ $bar }}"></div>
                        <div class="flex gap-2 pt-1"><div class="h-7 w-24 rounded-full bg-line-200"></div><div class="h-7 w-20 rounded-full bg-line-200"></div></div>
                        <div class="space-y-2 pt-3"><div class="h-3.5 w-full {{ $bar }}"></div><div class="h-3.5 w-11/12 {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div></div>
                    </div>
                    <div class="h-64 md:col-span-2 xl:col-span-3 {{ $box }} p-4">
                        <div class="flex justify-between"><div class="h-4 w-16 {{ $bar }}"></div><div class="h-6 w-28 {{ $bar }}"></div></div>
                        <div class="mt-6 h-11 rounded-card bg-line-200"></div>
                        <div class="mt-6 space-y-2"><div class="h-3 w-5/6 {{ $bar }}"></div><div class="h-3 w-2/3 {{ $bar }}"></div></div>
                    </div>
                </div>
            </template>

            {{-- Cart and checkout: lines on one side, a summary card on the other. --}}
            <template x-if="kind === 'cart'">
                <div class="mt-4 grid gap-6 xl:grid-cols-12">
                    <div class="space-y-3 xl:col-span-8">
                        <div class="h-7 w-48 {{ $bar }}"></div>
                        @foreach (range(1, 3) as $line)
                            <div class="flex items-center gap-3 p-4 {{ $box }}">
                                <div class="size-16 shrink-0 rounded-md bg-line-200"></div>
                                <div class="flex-1 space-y-2"><div class="h-3.5 w-2/3 {{ $bar }}"></div><div class="h-3 w-1/3 {{ $bar }}"></div></div>
                                <div class="h-9 w-24 rounded-full bg-line-200"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="h-56 space-y-3 p-4 xl:col-span-4 {{ $box }}">
                        @foreach (range(1, 4) as $row)
                            <div class="flex justify-between"><div class="h-3.5 w-24 {{ $bar }}"></div><div class="h-3.5 w-20 {{ $bar }}"></div></div>
                        @endforeach
                        <div class="h-12 rounded-card bg-line-200"></div>
                    </div>
                </div>
            </template>

            {{-- Account: menu beside on desktop, a stack of cards. --}}
            <template x-if="kind === 'account'">
                <div class="mt-4 flex gap-6">
                    <div class="hidden h-80 w-64 shrink-0 xl:block {{ $box }}"></div>
                    <div class="flex-1 space-y-4">
                        <div class="h-7 w-40 {{ $bar }}"></div>
                        @foreach (range(1, 3) as $card)
                            <div class="space-y-3 p-4 {{ $box }}">
                                <div class="flex justify-between"><div class="h-4 w-40 {{ $bar }}"></div><div class="h-6 w-20 rounded-full bg-line-200"></div></div>
                                <div class="h-3.5 w-1/3 {{ $bar }}"></div>
                                <div class="flex items-center justify-between pt-1"><div class="h-4 w-24 {{ $bar }}"></div><div class="flex gap-1"><div class="size-10 rounded-full bg-line-200"></div><div class="size-10 rounded-full bg-line-200"></div></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>

            {{-- Text page: a title and paragraphs. --}}
            <template x-if="kind === 'text'">
                <div class="mt-4 max-w-3xl space-y-3">
                    <div class="h-8 w-72 max-w-full {{ $bar }}"></div>
                    @foreach (range(1, 3) as $paragraph)
                        <div class="space-y-2 pt-3">
                            <div class="h-3.5 w-full {{ $bar }}"></div>
                            <div class="h-3.5 w-11/12 {{ $bar }}"></div>
                            <div class="h-3.5 w-full {{ $bar }}"></div>
                            <div class="h-3.5 w-3/5 {{ $bar }}"></div>
                        </div>
                    @endforeach
                </div>
            </template>
        </div>
    </div>
    <p class="sr-only" role="status" x-text="loading ? 'در حال بارگذاری صفحه…' : ''"></p>
</div>
