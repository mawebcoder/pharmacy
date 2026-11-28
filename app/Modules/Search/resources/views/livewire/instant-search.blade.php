{{-- Search box with suggestions (roadmap D21.2 item 6): see App\Modules\Search\Livewire\InstantSearch.
     Matching products are cards in a swipeable row like «محصولات محبوب», at most 6 (owner, 2026-11-15). While the panel is open the page
     below the header is dimmed and lightly blurred (only opacity animates; owner, 2026-11-14); the magnifier turns
     into a spinner while suggestions load, and a skeleton stands in for «محصولات محبوب» until they arrive.
     «جستجوهای اخیر» come from the browser at once ($store.searchHistory) and, for a signed-in customer, are merged with
     their saved searches when popular products arrive (same request), with the admin's pinned categories and brands.
     While typing, categories and brands are rows of round icons (x-catalog::search-shortcuts; owner, 2026-11-15).
     Phones and tablets (owner, 2026-11-15): the box opens a full-screen search page — a back arrow beside the box, the
     same content below it — over the header and bottom nav (html[data-search-open], resources/css/app.css); desktop
     keeps the drop-down panel. --}}
@php
    $hasText = mb_strlen(trim($value)) >= $minLength;
    $suggestions = $hasText ? $this->suggestions : null;
    $popular = ! $hasText && $popularRequested ? $this->popular : null;
    $shortcuts = ! $hasText && $popularRequested ? $this->shortcuts : null;
    $hasShortcuts = $shortcuts !== null && ($shortcuts['categories']->isNotEmpty() || $shortcuts['brands']->isNotEmpty());
    $listId = $inputId.'-suggestions';
@endphp

<form
    action="{{ route('search') }}"
    method="get"
    role="search"
    @class(['relative', $class])
    x-bind:class="open && 'max-xl:fixed max-xl:inset-0 max-xl:z-[70] max-xl:bg-card'"
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
        close() {
            this.open = false;
            $refs.input.blur();
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
    {{-- A link (a product, «مشاهده همه», a category) closes the search at once, so the next page's skeleton shows
         while it loads (owner, 2026-11-15). --}}
    x-on:click="$event.target.closest('a[href]') && close()"
    x-on:keydown.escape="open = false; $refs.input.focus()"
    x-effect="document.documentElement.toggleAttribute('data-search-open', open)"
>
    {{-- The page below the header, dimmed and blurred while the panel is open; a click on it closes the panel. On
         phones the box sits below the header row, so the box (z-50) and its icons (z-51) stay above this layer. --}}
    <x-layout.header-backdrop show="open" close="open = false" layer="z-40" class="hidden xl:block" />

    <div class="flex h-full flex-col">
        {{-- Full screen (phones, tablets): the back arrow and the box on top, the content below. --}}
        <div class="flex items-center gap-1" x-bind:class="open && 'max-xl:border-b max-xl:border-line-200 max-xl:p-2 max-xl:pe-3'">
            <button type="button" x-show="open" x-cloak x-on:click="close()" class="grid size-11 shrink-0 place-items-center rounded-full text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700 xl:hidden" aria-label="بستن جستجو">
                <x-ts-icon icon="arrow-right" class="size-6" />
            </button>
            <div class="relative min-w-0 flex-1">
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
            </div>
        </div>

        @unless ($hasText)
            {{-- Empty box: «جستجوهای اخیر» (instant, from the browser), then «محصولات محبوب» (roadmap D21.2, S14): their
                 skeleton while they load, then one Swiper row with side arrows at every size (owner, 2026-11-14): about 2
                 cards with the next peeking, 3 on desktop. The panel opens hidden, so Swiper re-measures (observer). --}}
            <div
                id="{{ $listId }}"
                x-ref="list"
                x-show="open && ($store.searchHistory.items.length || loadingPopular || {{ $popular?->isNotEmpty() || $hasShortcuts ? 'true' : 'false' }})"
                x-cloak
                class="z-50 space-y-4 overflow-y-auto bg-card p-3 max-xl:min-h-0 max-xl:flex-1 max-xl:px-4 xl:absolute xl:top-full xl:left-1/2 xl:mt-2 xl:w-[44rem] xl:-translate-x-1/2 xl:max-h-[75vh] xl:rounded-card xl:border xl:border-line-200 xl:shadow-card"
            >
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
                                <x-ui.product-card-skeleton class="w-[calc((100%-12px)/2.15)] shrink-0 sm:w-[220px]" />
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
                        <div class="relative" wire:ignore x-data="slider({ slidesPerView: 'auto', spaceBetween: 12, observer: true, observeParents: true })">
                            <div x-ref="slider" class="swiper">
                                <ul class="swiper-wrapper">
                                    @foreach ($popular as $product)
                                        <li wire:key="popular-{{ $product->getId() }}" class="swiper-slide h-auto! w-[calc((100%-12px)/2.15)]! sm:w-[220px]!">
                                            <x-catalog::product-card :product="$product" />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
                        </div>
                    </section>
                @endif

                {{-- The admin's pinned categories and brands («تنظیمات فروشگاه» › «جستجو»; owner, 2026-11-15). --}}
                @if ($hasShortcuts)
                    <x-catalog::search-shortcuts :categories="$shortcuts['categories']" :brands="$shortcuts['brands']" />
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
                class="z-50 overflow-y-auto bg-card p-2 max-xl:min-h-0 max-xl:flex-1 max-xl:px-3 xl:absolute xl:top-full xl:left-1/2 xl:mt-2 xl:w-[44rem] xl:-translate-x-1/2 xl:max-h-[70vh] xl:rounded-card xl:border xl:border-line-200 xl:shadow-card"
            >
                {{-- Typed on the wrong keyboard layout (S39b): say what was searched instead. --}}
                @if ($suggestions->searchedFor !== null)
                    <p class="px-3 pt-2 pb-1 text-sm text-ink-500">نتایج برای <span class="font-bold text-ink-900">«{{ $suggestions->searchedFor }}»</span></p>
                @endif
                @if ($suggestions->isEmpty())
                    <p class="px-3 py-4 text-sm text-ink-500">
                        @if (! $suggestions->productsAvailable)
                            جستجوی محصولات موقتاً در دسترس نیست.
                        @elseif ($didYouMean = $this->didYouMean())
                            منظورتان <a href="{{ route('search', ['q' => $didYouMean]) }}" wire:navigate class="font-bold text-primary-700 hover:underline">«{{ $didYouMean }}»</a> بود؟
                        @else
                            پیشنهادی پیدا نشد؛ Enter را بزنید تا همه نتایج را ببینید.
                        @endif
                    </p>
                @endif

                {{-- Products as cards in one swipeable row, like «محصولات محبوب» (owner, 2026-11-15): at most 6, then «مشاهده
                     همه» opens the search page. The row is rebuilt when the results change (its key). --}}
                @if ($suggestions->products->isNotEmpty())
                    <section aria-label="محصولات" class="px-1 pb-1">
                        <div class="mb-2 flex items-center justify-between gap-2 px-1">
                            <p class="text-sm font-bold text-ink-900">محصولات</p>
                            <a href="{{ route('search', ['q' => $value]) }}" class="flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                                مشاهده همه
                                <x-ts-icon icon="chevron-left" class="size-4" />
                            </a>
                        </div>
                        <div wire:key="suggest-products-{{ $suggestions->products->modelKeys() === [] ? 'none' : implode('-', $suggestions->products->modelKeys()) }}" class="relative" wire:ignore x-data="slider({ slidesPerView: 'auto', spaceBetween: 12, observer: true, observeParents: true })">
                            <div x-ref="slider" class="swiper">
                                <ul class="swiper-wrapper">
                                    @foreach ($suggestions->products as $product)
                                        <li wire:key="suggest-product-{{ $product->getId() }}" class="swiper-slide h-auto! w-[calc((100%-12px)/2.15)]! sm:w-[220px]!">
                                            <x-catalog::product-card :product="$product" />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
                        </div>
                    </section>
                @endif

                {{-- Categories and brands of the results, or the admin's pinned ones when none match (owner, 2026-11-15). --}}
                <x-catalog::search-shortcuts
                    class="px-2 pt-3"
                    heading-class="px-1 text-xs font-bold text-ink-500"
                    :categories="$suggestions->categories"
                    :brands="$suggestions->brands"
                    :categories-title="$suggestions->pinnedCategories ? 'دسته‌بندی‌های پیشنهادی' : 'دسته‌بندی‌ها'"
                    :brands-title="$suggestions->pinnedBrands ? 'برندهای پیشنهادی' : 'برندها'"
                />

                @foreach ([
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
    </div>
</form>
