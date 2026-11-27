{{--
    While wire:navigate fetches the next page (owner, 2026-11-14): the content area under the header is replaced by a
    skeleton with the layout of the page being opened (owner, 2026-11-15: every skeleton matches its real page), until
    the new page swaps in (which replaces this layer with a fresh, hidden one). kindOf() picks the shape from the
    destination URL. Plain links and ordinary form posts (logout, the search form, the gateway) show it too, until the
    browser leaves. It shows only after 150 ms, so a page that arrives at once does not flash. Screen readers hear
    «در حال بارگذاری صفحه…». Plain Tailwind (animate-pulse), no extra package.
--}}
@php
    $bar = 'rounded-full bg-line-200';
    $box = 'rounded-card border border-line-200 bg-card';
    $listKinds = "['category', 'tag', 'collection', 'brand', 'search']";
    $accountKinds = "['notifications', 'orders', 'order', 'addresses', 'profile', 'wishlist', 'reviews']";
@endphp

<div
    x-data="{
        loading: false,
        kind: 'category',
        timer: null,
        kindOf(url) {
            const path = new URL(url, location.origin).pathname.replace(/\/+$/, '') || '/';
            const exact = {
                '/': 'home', '/logout': 'home', '/cart': 'cart', '/checkout': 'review', '/checkout/address': 'address',
                '/login': 'login', '/brands': 'brands', '/categories': 'categories', '/collections': 'collections',
                '/search': 'search', '/faq': 'text', '/contact': 'text', '/track': 'result', '/account': 'orders',
                '/account/orders': 'orders', '/account/addresses': 'addresses', '/account/profile': 'profile',
                '/account/wishlist': 'wishlist', '/account/reviews': 'reviews',
            };
            if (exact[path]) return exact[path];
            const prefixes = [
                ['/products/', 'product'], ['/brands/', 'brand'], ['/categories/', 'category'], ['/collections/', 'collection'],
                ['/t/', 'tag'], ['/pages/', 'text'], ['/account/orders/', 'order'], ['/checkout/result/', 'result'], ['/payment/', 'result'],
            ];
            return prefixes.find(([prefix]) => path.startsWith(prefix))?.[1] ?? 'category';
        },
        start(url) {
            this.kind = this.kindOf(url);
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.loading = true), 150);
        },
        stop() {
            clearTimeout(this.timer);
            this.loading = false;
        },
        is(kinds) {
            return kinds.includes(this.kind);
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
    x-on:livewire:navigate.document="start($event.detail?.url ?? location.href)"
    x-on:livewire:navigated.document="stop()"
    x-on:click.document="showForLink($event)"
    x-on:submit.document="showForForm($event)"
    x-on:pageshow.window="stop()"
>
    <div
        x-show="loading"
        x-cloak
        x-transition:enter="transition-opacity duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-x-0 top-[var(--header-height)] bottom-0 z-35 overflow-hidden bg-surface-50 [contain:strict]"
        aria-hidden="true"
    >
        {{-- z-35: above the page's own sticky and fixed bars (z-30), below the header and bottom nav (z-40). Same frame
             as <main> in layouts.app. --}}
        <div class="mx-auto max-w-7xl animate-pulse px-4 py-6 motion-reduce:animate-none md:px-6 xl:px-8">

            {{-- Home: a product rail (heading + cards, 5 on desktop), the banner slider, a rail of round categories. --}}
            <template x-if="kind === 'home'">
                <div class="space-y-8 xl:space-y-10">
                    <div>
                        <div class="mb-3 flex min-h-11 items-center justify-between"><div class="h-5 w-40 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div></div>
                        <div class="flex gap-3 overflow-hidden md:gap-4">
                            @foreach (range(1, 5) as $card)
                                <x-ui.product-card-skeleton @class(['w-[45%] shrink-0 md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-64px)/5)]', 'hidden md:block' => $card === 4, 'hidden xl:block' => $card === 5]) />
                            @endforeach
                        </div>
                    </div>
                    <div class="aspect-[2/1] w-full rounded-card bg-line-200 md:aspect-[3/1]"></div>
                    <div>
                        <div class="mb-3 flex min-h-11 items-center"><div class="h-5 w-32 {{ $bar }}"></div></div>
                        <div class="flex overflow-hidden">
                            @foreach (range(1, 6) as $category)
                                <div @class(['w-[30%] shrink-0 flex-col items-center gap-2 p-2 md:w-[calc((100%-64px)/5)] xl:w-[calc((100%-80px)/6)]', 'flex' => $category !== 6, 'hidden xl:flex' => $category === 6])>
                                    <div class="size-20 rounded-full bg-line-200 xl:size-24"></div>
                                    <div class="h-3.5 w-16 {{ $bar }}"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>

            {{-- Product lists: a head per page, then the shared toolbar, filter sidebar (desktop) and grid. --}}
            <template x-if="is({{ $listKinds }})">
                <div class="space-y-6">
                    <template x-if="kind !== 'search'">
                        <div class="flex gap-2"><div class="h-4 w-10 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div><div class="h-4 w-24 {{ $bar }}"></div></div>
                    </template>

                    <template x-if="kind === 'category'">
                        <div class="space-y-6">
                            <div class="h-8 w-56 {{ $bar }}"></div>
                            <div class="flex gap-2 overflow-hidden">
                                @foreach (range(1, 8) as $box)
                                    <div class="flex w-24 shrink-0 flex-col items-center gap-2 rounded-card border border-line-200 bg-card p-2.5 md:w-28"><div class="size-12 rounded-full bg-line-200"></div><div class="h-3 w-12 {{ $bar }}"></div></div>
                                @endforeach
                            </div>
                        </div>
                    </template>
                    <template x-if="kind === 'tag'">
                        <div class="flex items-center gap-4"><div class="size-16 shrink-0 rounded-card bg-line-200"></div><div class="space-y-2"><div class="h-8 w-48 {{ $bar }}"></div><div class="h-3.5 w-32 {{ $bar }}"></div></div></div>
                    </template>
                    <template x-if="kind === 'collection'">
                        <div class="space-y-2"><div class="h-8 w-56 {{ $bar }}"></div><div class="h-4 w-80 max-w-full {{ $bar }}"></div></div>
                    </template>
                    <template x-if="kind === 'brand'">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start">
                            <div class="size-16 shrink-0 rounded-full bg-line-200 md:size-20 xl:size-24"></div>
                            <div class="flex-1 space-y-2">
                                <div class="flex items-baseline gap-3"><div class="h-8 w-40 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div></div>
                                <div class="h-3.5 w-28 {{ $bar }}"></div>
                                <div class="space-y-2 pt-2"><div class="h-3.5 w-full max-w-3xl {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div></div>
                            </div>
                        </div>
                    </template>
                    <template x-if="kind === 'search'">
                        <div class="h-8 w-64 max-w-full {{ $bar }}"></div>
                    </template>

                    <div class="space-y-4">
                        {{-- «n کالا», then filter button + sort select on phones and tablets, sort pills on desktop. --}}
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-14 {{ $bar }}"></div>
                            <div class="ms-auto h-11 w-28 rounded-card bg-line-200 xl:hidden"></div>
                            <div class="h-11 w-32 rounded-md bg-line-200 xl:hidden"></div>
                            <div class="ms-auto hidden items-center gap-2 xl:flex">
                                <div class="h-4 w-16 {{ $bar }}"></div>
                                @foreach (range(1, 5) as $sort)
                                    <div class="h-9 w-20 rounded-full bg-line-200"></div>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-start gap-6">
                            <div class="hidden w-70 shrink-0 space-y-4 px-4 py-4 xl:block {{ $box }}">
                                @foreach ([5, 4, 3] as $options)
                                    <div class="space-y-3 border-b border-line-200 pb-4 last:border-0">
                                        <div class="h-4 w-24 {{ $bar }}"></div>
                                        @foreach (range(1, $options) as $option)
                                            <div class="flex items-center gap-2"><div class="size-5 rounded bg-line-200"></div><div class="h-3.5 w-28 {{ $bar }}"></div></div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div class="grid min-w-0 flex-1 grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-4">
                                @foreach (range(1, 8) as $card)
                                    <x-ui.product-card-skeleton @class(['hidden md:block' => $card > 4 && $card <= 6, 'hidden xl:block' => $card > 6]) />
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- «برندها»: title, then one grid of logo tiles (no letters since 2026-11-27). --}}
            <template x-if="kind === 'brands'">
                <div class="space-y-6">
                    <div class="space-y-2"><div class="h-8 w-32 {{ $bar }}"></div><div class="h-3.5 w-48 {{ $bar }}"></div></div>
                    <div>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                            @foreach (range(1, 10) as $tile)
                                <div @class(['flex-col items-center gap-2 p-4', 'flex' => $tile <= 4, 'hidden md:flex' => $tile > 4 && $tile <= 6, 'hidden xl:flex' => $tile > 6])>
                                    <div class="size-28 rounded-full bg-line-200 md:size-32"></div>
                                    <div class="h-4 w-2/3 {{ $bar }}"></div>
                                    <div class="h-3 w-1/2 {{ $bar }}"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>

            {{-- «دسته‌بندی‌ها»: title, a grid of top-level tiles with a round icon. --}}
            <template x-if="kind === 'categories'">
                <div class="space-y-4">
                    <div class="h-8 w-40 {{ $bar }}"></div>
                    <div class="grid grid-cols-3 gap-3 md:grid-cols-4 xl:grid-cols-6">
                        @foreach (range(1, 12) as $tile)
                            <div @class(['flex-col items-center gap-2 p-3', $box, 'flex' => $tile <= 9, 'hidden md:flex' => $tile > 9])>
                                <div class="size-16 rounded-full bg-line-200 md:size-20"></div>
                                <div class="h-3.5 w-16 {{ $bar }}"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>

            {{-- «مجموعه‌ها»: title, a grid of rows (icon, name, count). --}}
            <template x-if="kind === 'collections'">
                <div class="space-y-4">
                    <div class="h-8 w-40 {{ $bar }}"></div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach (range(1, 6) as $collection)
                            <div class="flex min-h-16 items-center gap-3 px-4 py-3 {{ $box }}">
                                <div class="size-10 shrink-0 rounded-full bg-line-200"></div>
                                <div class="flex-1 space-y-2"><div class="h-4 w-1/2 {{ $bar }}"></div><div class="h-3 w-1/4 {{ $bar }}"></div></div>
                                <div class="size-5 rounded bg-line-200"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>

            {{-- Product page (B9.5): breadcrumb, gallery 5/12, info 4/12, buy box 3/12, then the tabs. --}}
            <template x-if="kind === 'product'">
                <div class="space-y-6">
                    <div class="flex gap-2"><div class="h-4 w-10 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div><div class="h-4 w-32 {{ $bar }}"></div></div>
                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-12 xl:gap-8">
                        <div class="xl:col-span-5">
                            <div class="aspect-square rounded-card border border-line-200 bg-line-200"></div>
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
                        <div class="h-64 p-4 md:col-span-2 xl:col-span-3 {{ $box }}">
                            <div class="flex justify-between"><div class="h-4 w-16 {{ $bar }}"></div><div class="h-6 w-28 {{ $bar }}"></div></div>
                            <div class="mt-6 h-11 rounded-card bg-line-200"></div>
                            <div class="mt-6 space-y-2"><div class="h-3 w-5/6 {{ $bar }}"></div><div class="h-3 w-2/3 {{ $bar }}"></div></div>
                        </div>
                    </div>
                    <div class="flex gap-2 overflow-hidden border-b border-line-200 pb-2">
                        @foreach (range(1, 5) as $tab)
                            <div class="h-9 w-24 shrink-0 rounded-md bg-line-200"></div>
                        @endforeach
                    </div>
                </div>
            </template>

            {{-- «سبد خرید»: title (+ «حذف همه»), lines 8/12, free-shipping and summary cards 4/12. --}}
            <template x-if="kind === 'cart'">
                <div class="space-y-4">
                    <div class="flex items-center justify-between"><div class="h-7 w-32 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div></div>
                    <div class="grid gap-4 xl:grid-cols-12 xl:items-start xl:gap-6">
                        <div class="divide-y divide-line-200 xl:col-span-8 {{ $box }}">
                            @foreach (range(1, 3) as $line)
                                <div class="flex gap-3 p-4">
                                    <div class="size-16 shrink-0 rounded-md bg-line-200"></div>
                                    <div class="flex-1 space-y-2"><div class="h-3 w-16 {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div><div class="h-4 w-24 {{ $bar }}"></div></div>
                                    <div class="flex flex-col items-end justify-between gap-2"><div class="h-11 w-28 rounded-full bg-line-200"></div><div class="flex items-center gap-2"><div class="h-4 w-20 {{ $bar }}"></div><div class="size-6 rounded bg-line-200"></div></div></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="space-y-3 xl:col-span-4">
                            <div class="space-y-2 p-4 {{ $box }}"><div class="h-4 w-40 {{ $bar }}"></div><div class="h-3 w-52 {{ $bar }}"></div><div class="h-2 rounded-full bg-line-200"></div></div>
                            <div class="space-y-3 p-4 {{ $box }}">
                                @foreach (range(1, 4) as $row)
                                    <div class="flex justify-between"><div class="h-3.5 w-24 {{ $bar }}"></div><div class="h-3.5 w-20 {{ $bar }}"></div></div>
                                @endforeach
                                <div class="h-12 rounded-card bg-line-200"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- The checkout stepper, shared by the address and review steps. --}}
            @php
                $stepper = '<div class="flex items-center gap-2">'
                    .'<div class="h-4 w-20 '.$bar.'"></div><div class="h-px w-8 bg-line-200"></div>'
                    .'<div class="h-4 w-24 '.$bar.'"></div><div class="h-px w-8 bg-line-200"></div>'
                    .'<div class="h-4 w-16 '.$bar.'"></div></div>';
            @endphp

            {{-- Checkout «آدرس ارسال» (max-w-4xl): back link, stepper, title, count + add button, address cards, the button. --}}
            <template x-if="kind === 'address'">
                <div class="mx-auto max-w-4xl space-y-4">
                    <div class="-mb-2 flex h-11 items-center"><div class="h-4 w-24 {{ $bar }}"></div></div>
                    {!! $stepper !!}
                    <div class="space-y-2"><div class="h-7 w-64 max-w-full {{ $bar }}"></div><div class="h-3.5 w-96 max-w-full {{ $bar }}"></div></div>
                    <div class="flex items-center justify-between"><div class="h-4 w-24 {{ $bar }}"></div><div class="h-11 w-36 rounded-card bg-line-200"></div></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (range(1, 2) as $address)
                            <div class="space-y-3 p-4 {{ $box }}">
                                <div class="flex justify-between"><div class="h-4 w-24 {{ $bar }}"></div><div class="h-6 w-16 rounded-full bg-line-200"></div></div>
                                <div class="space-y-2"><div class="h-3.5 w-full {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div><div class="h-3.5 w-1/2 {{ $bar }}"></div></div>
                                <div class="flex gap-2 pt-1"><div class="h-9 w-20 rounded-card bg-line-200"></div><div class="h-9 w-16 rounded-card bg-line-200"></div></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end border-t border-line-200 pt-4"><div class="h-12 w-full rounded-card bg-line-200 md:w-56"></div></div>
                </div>
            </template>

            {{-- Checkout «بازبینی و پرداخت»: stepper, title, sections 8/12 and the summary 4/12. --}}
            <template x-if="kind === 'review'">
                <div class="space-y-4">
                    {!! $stepper !!}
                    <div class="h-7 w-48 {{ $bar }}"></div>
                    <div class="grid gap-4 xl:grid-cols-12 xl:items-start">
                        <div class="space-y-4 xl:col-span-8">
                            <div class="space-y-2 p-4 {{ $box }}"><div class="flex justify-between"><div class="h-5 w-28 {{ $bar }}"></div><div class="h-4 w-12 {{ $bar }}"></div></div><div class="h-3.5 w-1/3 {{ $bar }}"></div><div class="h-3.5 w-5/6 {{ $bar }}"></div></div>
                            <div class="flex justify-between p-4 {{ $box }}"><div class="h-5 w-28 {{ $bar }}"></div><div class="h-4 w-20 {{ $bar }}"></div></div>
                            <div class="flex min-h-12 items-center justify-between px-4 {{ $box }}"><div class="h-5 w-24 {{ $bar }}"></div><div class="size-5 rounded bg-line-200"></div></div>
                            <div class="flex gap-2 p-4 {{ $box }}"><div class="h-11 flex-1 rounded-card bg-line-200"></div><div class="h-11 w-24 rounded-card bg-line-200"></div></div>
                        </div>
                        <div class="space-y-3 p-4 xl:col-span-4 {{ $box }}">
                            @foreach (range(1, 4) as $row)
                                <div class="flex justify-between"><div class="h-3.5 w-24 {{ $bar }}"></div><div class="h-3.5 w-20 {{ $bar }}"></div></div>
                            @endforeach
                            <div class="h-12 rounded-card bg-line-200"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Payment result and the gateway: one centred card. --}}
            <template x-if="kind === 'result'">
                <div class="mx-auto max-w-lg">
                    <div class="flex flex-col items-center p-6 md:p-8 {{ $box }}">
                        <div class="size-16 rounded-full bg-line-200"></div>
                        <div class="mt-4 h-6 w-48 {{ $bar }}"></div>
                        <div class="mt-3 h-3.5 w-64 max-w-full {{ $bar }}"></div>
                        <div class="mt-6 flex w-full gap-2"><div class="h-11 flex-1 rounded-card bg-line-200"></div><div class="h-11 flex-1 rounded-card bg-line-200"></div></div>
                    </div>
                </div>
            </template>

            {{-- Sign in: one centred 400 px card. --}}
            <template x-if="kind === 'login'">
                <div class="flex justify-center py-6 md:py-12">
                    <div class="w-full max-w-[400px] p-6 md:p-8 {{ $box }}">
                        <div class="flex flex-col items-center gap-3"><div class="size-12 rounded-card bg-line-200"></div><div class="h-6 w-40 {{ $bar }}"></div></div>
                        <div class="mt-6 space-y-4">
                            <div class="mx-auto h-3.5 w-56 {{ $bar }}"></div>
                            <div class="space-y-1.5"><div class="h-3.5 w-20 {{ $bar }}"></div><div class="h-12 rounded-card bg-line-200"></div></div>
                            <div class="h-12 rounded-card bg-line-200"></div>
                            <div class="mx-auto h-3 w-64 max-w-full {{ $bar }}"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Text page: breadcrumb, then the title and paragraphs in one centred card. --}}
            <template x-if="kind === 'text'">
                <div class="mx-auto max-w-3xl">
                    <div class="mb-4 flex gap-2"><div class="h-4 w-10 {{ $bar }}"></div><div class="h-4 w-24 {{ $bar }}"></div></div>
                    <div class="space-y-3 px-4 py-6 md:px-8 md:py-8 {{ $box }}">
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
                </div>
            </template>

            {{-- Account (B9.9): the shared frame (sidebar 3/12 on desktop; the menu card or a back link on phones), then
                 each page's content. --}}
            <template x-if="is({{ $accountKinds }})">
                <div class="grid gap-6 xl:grid-cols-12">
                    <div class="hidden xl:col-span-3 xl:block">
                        <div class="{{ $box }}">
                            <div class="flex items-center gap-3 border-b border-line-200 p-4"><div class="size-11 shrink-0 rounded-full bg-line-200"></div><div class="space-y-2"><div class="h-4 w-28 {{ $bar }}"></div><div class="h-3.5 w-24 {{ $bar }}"></div></div></div>
                            <div class="divide-y divide-line-200">
                                @foreach ([20, 16, 16, 24, 20, 24, 16] as $width)
                                    <div class="flex min-h-12 items-center gap-3 px-4"><div class="size-5 rounded bg-line-200"></div><div class="h-3.5 {{ $bar }}" style="width: {{ $width * 4 }}px"></div></div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 xl:col-span-9">
                        <div class="flex gap-2 overflow-hidden xl:hidden">@foreach ([24, 20, 24, 20] as $width)<div class="h-11 shrink-0 rounded-full bg-line-200" style="width: {{ $width * 4 }}px"></div>@endforeach</div>
                        <div class="h-7 w-40 {{ $bar }}"></div>

                        <template x-if="kind === 'orders'">
                            <div class="space-y-3">
                                @foreach (range(1, 3) as $order)
                                    <div class="p-4 {{ $box }}">
                                        <div class="flex items-center justify-between"><div class="h-4 w-36 {{ $bar }}"></div><div class="h-6 w-20 rounded-full bg-line-200"></div></div>
                                        <div class="mt-2 h-3.5 w-40 {{ $bar }}"></div>
                                        <div class="mt-3 flex items-center justify-between"><div class="flex -space-x-2">@foreach (range(1, 3) as $thumb)<div class="size-11 rounded-full border-2 border-card bg-line-200"></div>@endforeach</div><div class="h-5 w-24 {{ $bar }}"></div></div>
                                        <div class="mt-3 flex gap-2 border-t border-line-200 pt-3"><div class="h-9 w-24 rounded-card bg-line-200"></div><div class="h-9 w-20 rounded-card bg-line-200"></div></div>
                                    </div>
                                @endforeach
                            </div>
                        </template>

                        <template x-if="kind === 'order'">
                            <div class="space-y-4">
                                <div class="hidden h-11 items-center xl:flex"><div class="h-4 w-28 {{ $bar }}"></div></div>
                                <div class="p-4 md:p-6 {{ $box }}">
                                    <div class="flex items-center justify-between"><div class="h-6 w-40 {{ $bar }}"></div><div class="h-6 w-24 rounded-full bg-line-200"></div></div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-3">@foreach (range(1, 3) as $fact)<div class="space-y-1.5"><div class="h-3.5 w-16 {{ $bar }}"></div><div class="h-4 w-24 {{ $bar }}"></div></div>@endforeach</div>
                                    <div class="mt-4 flex gap-2 border-t border-line-200 pt-4"><div class="h-9 w-24 rounded-card bg-line-200"></div><div class="h-9 w-20 rounded-card bg-line-200"></div></div>
                                </div>
                                <div class="grid gap-4 xl:grid-cols-3 xl:items-start">
                                    <div class="space-y-4 xl:col-span-2">
                                        <div class="divide-y divide-line-200 {{ $box }}">
                                            <div class="p-4"><div class="h-5 w-24 {{ $bar }}"></div></div>
                                            @foreach (range(1, 2) as $item)
                                                <div class="flex items-center gap-3 p-4"><div class="size-14 shrink-0 rounded-md bg-line-200"></div><div class="flex-1 space-y-2"><div class="h-3.5 w-2/3 {{ $bar }}"></div><div class="h-3 w-1/3 {{ $bar }}"></div></div><div class="h-4 w-20 {{ $bar }}"></div></div>
                                            @endforeach
                                        </div>
                                        <div class="space-y-3 p-4 {{ $box }}"><div class="h-5 w-28 {{ $bar }}"></div>@foreach (range(1, 3) as $step)<div class="flex items-center gap-3"><div class="size-3 rounded-full bg-line-200"></div><div class="h-3.5 w-40 {{ $bar }}"></div></div>@endforeach</div>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-2 p-4 {{ $box }}"><div class="h-5 w-24 {{ $bar }}"></div><div class="h-3.5 w-full {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div></div>
                                        <div class="space-y-3 p-4 {{ $box }}"><div class="h-5 w-28 {{ $bar }}"></div>@foreach (range(1, 4) as $row)<div class="flex justify-between"><div class="h-3.5 w-20 {{ $bar }}"></div><div class="h-3.5 w-16 {{ $bar }}"></div></div>@endforeach</div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="kind === 'addresses'">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between"><div class="h-4 w-24 {{ $bar }}"></div><div class="h-11 w-36 rounded-card bg-line-200"></div></div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    @foreach (range(1, 2) as $address)
                                        <div class="space-y-3 p-4 {{ $box }}">
                                            <div class="flex justify-between"><div class="h-4 w-24 {{ $bar }}"></div><div class="h-6 w-16 rounded-full bg-line-200"></div></div>
                                            <div class="space-y-2"><div class="h-3.5 w-full {{ $bar }}"></div><div class="h-3.5 w-2/3 {{ $bar }}"></div><div class="h-3.5 w-1/2 {{ $bar }}"></div></div>
                                            <div class="flex gap-2 pt-1"><div class="h-9 w-20 rounded-card bg-line-200"></div><div class="h-9 w-16 rounded-card bg-line-200"></div></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </template>

                        <template x-if="kind === 'profile'">
                            <div class="space-y-4">
                                <div class="space-y-5 p-4 md:p-6 {{ $box }}">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        @foreach (range(1, 6) as $field)
                                            <div class="space-y-1.5"><div class="h-3.5 w-20 {{ $bar }}"></div><div class="h-11 rounded-card bg-line-200"></div></div>
                                        @endforeach
                                    </div>
                                    <div class="flex gap-4"><div class="h-5 w-16 {{ $bar }}"></div><div class="h-5 w-16 {{ $bar }}"></div></div>
                                    <div class="h-11 w-32 rounded-card bg-line-200"></div>
                                </div>
                                <div class="space-y-3 p-4 md:p-6 {{ $box }}"><div class="h-4 w-24 {{ $bar }}"></div><div class="grid grid-cols-3 gap-3">@foreach (range(1, 3) as $theme)<div class="h-11 rounded-card bg-line-200"></div>@endforeach</div></div>
                            </div>
                        </template>

                        <template x-if="kind === 'wishlist'">
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:gap-4">
                                @foreach (range(1, 6) as $card)
                                    <div @class(['space-y-2', 'hidden md:block' => $card > 4])>
                                        <x-ui.product-card-skeleton />
                                        <div class="h-11 {{ $box }}"></div>
                                    </div>
                                @endforeach
                            </div>
                        </template>

                        <template x-if="kind === 'reviews'">
                            <div class="flex flex-col items-center px-6 py-12 {{ $box }}"><div class="size-14 rounded-full bg-line-200"></div><div class="mt-4 h-4 w-40 {{ $bar }}"></div><div class="mt-2 h-3.5 w-56 max-w-full {{ $bar }}"></div></div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <p class="sr-only" role="status" x-text="loading ? 'در حال بارگذاری صفحه…' : ''"></p>
</div>
