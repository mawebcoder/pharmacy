{{-- Search box with suggestions (roadmap D21.2 item 6): see App\Modules\Search\Livewire\InstantSearch.
     Product rows show the default SKU's price and a small «+» that adds it (S19). While the panel is open the page
     below the header is dimmed and lightly blurred (only opacity animates; owner, 2026-11-14); the magnifier turns
     into a spinner while suggestions load, and a skeleton stands in for «محصولات محبوب» until they arrive.
     «جستجوهای اخیر» come from the browser at once ($store.searchHistory) and, for a signed-in customer, are merged with
     their saved searches when popular products arrive (same request). --}}
@use('App\Modules\Catalog\Support\ProductCardData')
@php
    $hasText = mb_strlen(trim($value)) >= $minLength;
    $suggestions = $hasText ? $this->suggestions : null;
    $popular = ! $hasText && $popularRequested ? $this->popular : null;
    $listId = $inputId.'-suggestions';
@endphp

<form
    action="{{ route('search') }}"
    method="get"
    role="search"
    @class(['relative', $class])
    x-data="{
        open: false,
        loadingPopular: false,
        askForPopular() {
            if ($wire.popularRequested || this.loadingPopular) return;
            this.loadingPopular = true;
            $wire.set('popularRequested', true)
                .then(() => $store.searchHistory.merge($wire.serverRecent ?? []))
                .finally(() => (this.loadingPopular = false));
        },
        clearRecent() {
            $store.searchHistory.clear();
            $wire.clearRecent();
        },
        searchUrl(q) {
            return @js(route('search')) + '?q=' + encodeURIComponent(q);
        },
        move(step) {
            const items = [...$refs.list?.querySelectorAll('[role=option]') ?? []];
            if (! items.length) return;
            const index = items.indexOf(document.activeElement);
            const next = items[(index + step + items.length) % items.length];
            next.focus();
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape="open = false; $refs.input.focus()"
>
    {{-- The page below the header, dimmed and blurred while the panel is open; a click on it closes the panel. On
         phones the box sits below the header row, so the box (z-50) and its icons (z-51) stay above this layer. --}}
    <div
        x-show="open"
        x-cloak
        x-on:click="open = false"
        x-transition:enter="transition-opacity duration-150 ease-out motion-reduce:transition-none"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-100 ease-in motion-reduce:transition-none"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-x-0 top-[var(--header-height)] bottom-0 z-40 bg-ink-900/25 backdrop-blur-[3px] [contain:strict]"
        aria-hidden="true"
    ></div>

    <label for="{{ $inputId }}" class="sr-only">جستجو در محصولات</label>
    {{-- The magnifier turns into a spinner while suggestions load (the browser's own «×» keeps the end side). --}}
    <x-ts-icon icon="magnifying-glass" wire:loading.remove wire:target="value" class="pointer-events-none absolute inset-y-0 start-3 z-[51] my-auto size-5 text-ink-500" />
    <span wire:loading.flex wire:target="value" class="pointer-events-none absolute inset-y-0 start-3 z-[51] items-center text-primary-700" role="status">
        <x-ui.spinner class="size-5" />
        <span class="sr-only">در حال جستجو…</span>
    </span>
    <input
        id="{{ $inputId }}"
        x-ref="input"
        name="q"
        type="search"
        autocomplete="off"
        maxlength="120"
        placeholder="جستجوی محصول، برند یا ماده مؤثره"
        wire:model.live.debounce.300ms="value"
        x-on:focus="open = true; askForPopular()"
        x-on:input="open = true"
        x-on:keydown.down.prevent="open = true; $nextTick(() => move(1))"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="{{ $listId }}"
        x-bind:aria-expanded="open && ({{ $hasText || ($popular?->isNotEmpty() ?? false) ? 'true' : 'false' }} || $store.searchHistory.items.length > 0)"
        class="relative z-50 h-11 w-full rounded-full border-0 bg-surface-50 ps-10 pe-4 text-sm text-ink-900 ring-1 ring-line-300 placeholder:text-ink-500 focus:bg-card focus:ring-2 focus:ring-primary-700"
    >

    @unless ($hasText)
        {{-- Empty box: «جستجوهای اخیر» (instant, from the browser), then «محصولات محبوب» (roadmap D21.2, S13b): their
             skeleton while they load, then one Swiper row with side arrows at every size (owner, 2026-11-14): about 2
             cards with the next peeking, 3 on desktop. The panel opens hidden, so Swiper re-measures (observer). --}}
        <div
            id="{{ $listId }}"
            x-ref="list"
            x-show="open && ($store.searchHistory.items.length || loadingPopular || {{ $popular?->isNotEmpty() ? 'true' : 'false' }})"
            x-cloak
            class="absolute inset-x-0 top-full z-50 mt-2 max-h-[65vh] space-y-4 overflow-y-auto rounded-card border border-line-200 bg-card p-3 shadow-card md:max-h-[75vh]"
        >
            {{-- Phones: tapping beside the panel is hard, so it has its own «بستن». --}}
            <div class="sticky -top-3 z-10 -mx-3 -mt-3 flex justify-end bg-card px-3 md:hidden">
                <button type="button" x-on:click="open = false; $refs.input.blur()" class="flex min-h-11 items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-900 focus-visible:outline-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="x-mark" class="size-5" />
                    بستن
                </button>
            </div>
            <template x-if="$store.searchHistory.items.length">
                <section aria-label="جستجوهای اخیر">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-ink-900">جستجوهای اخیر</p>
                        <button type="button" x-on:click="clearRecent()" class="flex min-h-11 items-center gap-1 text-sm text-ink-500 hover:text-danger-600 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="trash" class="size-4" />
                            پاک کردن
                        </button>
                    </div>
                    <ul class="flex flex-wrap gap-2">
                        <template x-for="item in $store.searchHistory.items" x-bind:key="item.q">
                            <li>
                                <a role="option" x-bind:href="searchUrl(item.q)" wire:navigate class="inline-flex h-9 max-w-56 items-center gap-1.5 rounded-full border border-line-300 px-3 text-sm text-ink-900 hover:border-primary-700 hover:text-primary-700 focus:border-primary-700 focus:bg-primary-50 focus:outline-none">
                                    <x-ts-icon icon="clock" class="size-4 shrink-0 text-ink-500" />
                                    <span class="truncate" x-text="item.q"></span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>

            {{-- Skeleton of «محصولات محبوب» while they load (only in the page while needed). --}}
            <template x-if="loadingPopular">
                <div class="animate-pulse [contain:content] motion-reduce:animate-none" aria-hidden="true">
                    <div class="mb-3 flex items-center justify-between"><div class="h-4 w-28 rounded-full bg-line-200"></div><div class="h-4 w-20 rounded-full bg-line-200"></div></div>
                    <div class="flex gap-3 overflow-hidden">
                        @foreach (range(1, 3) as $card)
                            <div class="w-[42%] shrink-0 overflow-hidden rounded-card border border-line-200 xl:w-[calc((100%-24px)/3)]">
                                <div class="aspect-square bg-line-200"></div>
                                <div class="space-y-2 p-2"><div class="h-3 w-5/6 rounded-full bg-line-200"></div><div class="h-3 w-1/2 rounded-full bg-line-200"></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>

            @if ($popular !== null && $popular->isNotEmpty())
                <section aria-label="محصولات محبوب">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-ink-900">محصولات محبوب</p>
                        <a href="{{ route('collections.show', 'popular') }}" wire:navigate class="flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                            مشاهده همه
                            <x-ts-icon icon="chevron-left" class="size-4" />
                        </a>
                    </div>
                    <div class="relative" wire:ignore x-data="slider({ slidesPerView: 2.3, spaceBetween: 12, observer: true, observeParents: true, breakpoints: { 1280: { slidesPerView: 3, spaceBetween: 12 } } })">
                        <div x-ref="slider" class="swiper">
                            <ul class="swiper-wrapper">
                                @foreach ($popular as $product)
                                    <li wire:key="popular-{{ $product->getId() }}" class="swiper-slide h-auto! w-[42%] xl:w-[calc((100%-24px)/3)]">
                                        <x-catalog::product-card :product="$product" :quick-view="false" />
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
                    </div>
                </section>
            @endif
        </div>
    @endunless

    @if ($suggestions !== null)
        <div
            id="{{ $listId }}"
            x-ref="list"
            x-show="open"
            x-cloak
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            role="listbox"
            aria-label="پیشنهادهای جستجو"
            class="absolute inset-x-0 top-full z-50 mt-2 max-h-[60vh] overflow-y-auto rounded-card border border-line-200 bg-card p-2 shadow-card md:max-h-[70vh]"
        >
            <div class="sticky -top-2 z-10 -mx-2 -mt-2 flex justify-end bg-card px-2 md:hidden">
                <button type="button" x-on:click="open = false; $refs.input.blur()" class="flex min-h-11 items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-900 focus-visible:outline-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="x-mark" class="size-5" />
                    بستن
                </button>
            </div>
            @if ($suggestions->isEmpty())
                <p class="px-3 py-4 text-sm text-ink-500">
                    @if (! $suggestions->productsAvailable)
                        جستجوی محصولات موقتاً در دسترس نیست.
                    @else
                        پیشنهادی پیدا نشد؛ Enter را بزنید تا همه نتایج را ببینید.
                    @endif
                </p>
            @endif

            @if ($suggestions->products->isNotEmpty())
                <p class="px-3 pt-2 pb-1 text-xs font-bold text-ink-500">محصولات</p>
                <ul>
                    @foreach ($suggestions->products as $product)
                        @php($card = ProductCardData::for($product))
                        <li wire:key="suggest-product-{{ $product->getId() }}" class="flex items-center gap-1">
                            <a role="option" href="{{ route('products.show', $product) }}" wire:navigate class="flex min-h-14 min-w-0 flex-1 items-center gap-3 rounded-md px-3 py-1 hover:bg-surface-50 focus:bg-primary-50 focus:outline-none">
                                <span class="size-11 shrink-0 overflow-hidden rounded-md bg-media">
                                    <x-media::picture :media="$product->getMainImage()" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-1 text-sm text-ink-900">{{ $product->getNameFa() }}</span>
                                    <span class="block text-xs text-ink-500">{{ $product->getBrand()?->getNameFa() }}</span>
                                </span>
                                @if ($card->price !== null && $card->inStock)
                                    <x-ui.price :price="$card->price" :old="$card->oldPrice" size="sm" class="shrink-0" />
                                @else
                                    <span class="shrink-0 text-xs text-ink-500">ناموجود</span>
                                @endif
                            </a>
                            @if ($card->variantId !== null && $card->inStock && $product->isSellableType() && ! $product->requiresPrescription())
                                <x-cart::add-button :variant-id="$card->variantId" :label="$product->getNameFa()" size="sm" class="me-1" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @foreach ([
                'برندها' => $suggestions->brands->map(fn ($brand) => [route('brands.show', $brand), $brand->getNameFa()]),
                'دسته‌بندی‌ها' => $suggestions->categories->map(fn ($category) => [route('category.show', $category), $category->getNameFa()]),
                'برچسب‌ها' => $suggestions->tags->map(fn ($tag) => [route('tags.show', $tag), $tag->getNameFa()]),
            ] as $heading => $links)
                @if ($links->isNotEmpty())
                    <p class="px-3 pt-3 pb-1 text-xs font-bold text-ink-500">{{ $heading }}</p>
                    <ul class="flex flex-wrap gap-1 px-1">
                        @foreach ($links as [$url, $label])
                            <li>
                                <a role="option" href="{{ $url }}" wire:navigate class="flex h-9 items-center rounded-full border border-line-200 px-3 text-sm text-ink-900 hover:border-primary-300 focus:border-primary-700 focus:bg-primary-50 focus:outline-none">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach

            <a role="option" href="{{ route('search', ['q' => $value]) }}" class="mt-2 flex min-h-11 items-center justify-center rounded-md text-sm font-bold text-primary-700 hover:bg-primary-50 focus:bg-primary-50 focus:outline-none">
                همه نتایج برای «{{ $value }}»
            </a>
        </div>
    @endif
</form>
