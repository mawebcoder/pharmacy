{{-- Product list (roadmap B9.3): see App\Modules\Catalog\Livewire\ProductListing. --}}
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $products = $result->products;
    $applied = $this->appliedFilters();
    $hasFilters = $result->facets !== [] || ! $result->degraded;
@endphp

<section aria-labelledby="listing-title" class="space-y-4">
    <h2 id="listing-title" class="sr-only">فهرست محصولات</h2>

    @if ($result->degraded)
        <div role="status" class="rounded-card bg-warning-500/10 px-4 py-3 text-sm text-ink-900">
            جستجو و فیلترها موقتاً در دسترس نیستند؛ جدیدترین محصولات نمایش داده می‌شوند.
        </div>
    @endif

    {{-- Toolbar: count, filters button (phone/tablet), sort --}}
    <div class="flex flex-wrap items-center gap-2">
        <p class="text-sm text-ink-500" aria-live="polite">{{ PersianDigits::toPersian($products->total()) }} کالا</p>

        @if ($hasFilters)
            <x-ts-button color="secondary" outline icon="adjustments-horizontal" class="ms-auto xl:hidden" x-on:click="$tsui.open.slide('listing-filters')">
                فیلترها
                @if ($applied !== [])
                    <span class="rounded-full bg-primary-700 px-2 text-xs text-white">{{ PersianDigits::toPersian(count($applied)) }}</span>
                @endif
            </x-ts-button>
        @endif

        <label class="sr-only" for="listing-sort">مرتب‌سازی</label>
        <select id="listing-sort" wire:model.live="sort" class="h-11 rounded-md border-0 bg-card px-3 text-sm ring-1 ring-line-300 focus:ring-2 focus:ring-primary-700 xl:hidden @unless ($hasFilters) ms-auto @endunless">
            @foreach ($sorts as $option)
                <option value="{{ $option->value }}" @selected($option === $currentSort)>{{ $option->label() }}</option>
            @endforeach
        </select>

        <nav aria-label="مرتب‌سازی" class="ms-auto hidden items-center gap-1 xl:flex">
            <span class="text-sm text-ink-500">مرتب‌سازی:</span>
            @foreach ($sorts as $option)
                <button type="button" wire:click="$set('sort', '{{ $option->value }}')"
                    @if ($option === $currentSort) aria-current="true" @endif
                    @class([
                        'h-9 rounded-full px-3 text-sm focus-visible:outline-2 focus-visible:outline-primary-700',
                        'bg-primary-700 text-white' => $option === $currentSort,
                        'text-ink-900 hover:bg-surface-50' => $option !== $currentSort,
                    ])>{{ $option->label() }}</button>
            @endforeach
        </nav>
    </div>

    {{-- Applied filters --}}
    @if ($applied !== [])
        <div class="scrollbar-none -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
            @foreach ($applied as $chip)
                <button type="button" wire:click="{{ $chip['action'] }}" class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-primary-50 px-3 text-sm text-primary-800 hover:bg-primary-100 focus-visible:outline-2 focus-visible:outline-primary-700">
                    {{ $chip['label'] }}
                    <x-ts-icon icon="x-mark" class="size-4" />
                    <span class="sr-only">حذف فیلتر</span>
                </button>
            @endforeach
            <button type="button" wire:click="clearFilters" class="h-9 shrink-0 px-2 text-sm text-danger-600 hover:underline">حذف همه</button>
        </div>
    @endif

    <div class="flex items-start gap-6">
        @if ($hasFilters)
            <aside aria-label="فیلترها" class="sticky top-[calc(var(--header-height)+1rem)] hidden max-h-[calc(100vh-var(--header-height)-2rem)] w-70 shrink-0 overflow-y-auto rounded-card border border-line-200 bg-card px-4 xl:block">
                @include('catalog::partials.filters', ['result' => $result, 'idPrefix' => 'sidebar'])
            </aside>
        @endif

        <div class="min-w-0 flex-1 space-y-6">
            {{-- Desktop: while a filter, sort or page change loads, skeleton cards stand in for the results (owner,
                 2026-11-15); phones keep the faded list, their filters sit in the sheet with its own spinner. --}}
            <div class="hidden xl:contents" aria-hidden="true">
                <ul wire:loading.grid class="hidden animate-pulse grid-cols-4 gap-4 motion-reduce:animate-none">
                    @foreach (range(1, 8) as $placeholder)
                        <li><x-ui.product-card-skeleton /></li>
                    @endforeach
                </ul>
            </div>

            @if ($products->isEmpty())
                {{-- Empty result, like the empty cart (owner, 2026-11-15): one clear way on — the same list without filters,
                     or the shop when nothing was filtered. --}}
                <div class="rounded-card border border-line-200 bg-card px-6 py-12 text-center" wire:loading.class="xl:hidden">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary-50 text-primary-700">
                        <x-ts-icon icon="magnifying-glass" class="size-7" />
                    </span>
                    <p class="mt-4 font-bold text-ink-900">کالایی با این مشخصات پیدا نشد</p>
                    @if ($applied !== [])
                        <p class="mt-1 text-sm text-ink-500">فیلترها را بردارید تا همه کالاهای این بخش را ببینید.</p>
                        <button type="button" wire:click="clearFilters" class="mt-5 inline-flex h-12 items-center justify-center gap-2 rounded-card bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="funnel" class="size-5" />
                            نمایش همه کالاها
                        </button>
                    @else
                        <p class="mt-1 text-sm text-ink-500">فعلاً کالایی در این بخش نیست.</p>
                        <a href="{{ route('home') }}" wire:navigate class="mt-5 inline-flex h-12 items-center justify-center rounded-card bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                            بازگشت به فروشگاه
                        </a>
                    @endif
                </div>
            @else
                <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-4" wire:loading.class="opacity-60 xl:hidden">
                    @foreach ($products as $product)
                        <li wire:key="listing-product-{{ $product->getId() }}">
                            <x-catalog::product-card :product="$product" :href="$this->productHref($product)" />
                        </li>
                    @endforeach
                </ul>

                {{-- Phones and tablets: «نمایش بیشتر»; desktop: numbered pages --}}
                @if ($products->hasMorePages() || $products->total() > $products->count())
                    <div class="flex justify-center xl:hidden">
                        @if ($products->count() < $products->total())
                            <x-ts-button color="primary" outline wire:click="loadMore" loading="loadMore">نمایش بیشتر</x-ts-button>
                        @endif
                    </div>
                @endif
                <div class="hidden xl:block">
                    {{ $products->onEachSide(1)->links(data: ['scrollTo' => '#listing-title']) }}
                </div>
            @endif
        </div>
    </div>

    @if ($hasFilters)
        <x-ts-slide id="listing-filters" title="فیلترها" bottom size="full">
            {{-- Drag this handle or the title row down to close (owner, 2026-11-15); «×» and the buttons close it too. --}}
            <div x-swipe-close="$tsui.close.slide('listing-filters')" class="-mt-2 mb-2 flex h-8 cursor-grab items-center justify-center" aria-hidden="true">
                <span class="h-1.5 w-12 rounded-full bg-line-300"></span>
            </div>
            @include('catalog::partials.filters', ['result' => $result, 'idPrefix' => 'sheet'])
            <x-slot:footer>
                <div class="flex w-full gap-2">
                    {{-- While a filter change loads, a small spinner stands in for the count (owner, 2026-11-15). --}}
                    <x-ts-button color="primary" class="flex-1" x-on:click="$tsui.close.slide('listing-filters')" wire:loading.attr="disabled" data-own-loading>
                        <span wire:loading.remove>نمایش {{ PersianDigits::toPersian($products->total()) }} کالا</span>
                        <span wire:loading.flex class="items-center gap-2" role="status">
                            <x-ui.spinner class="size-4" />
                            در حال به‌روزرسانی…
                        </span>
                    </x-ts-button>
                    <x-ts-button color="secondary" outline wire:click="clearFilters">حذف فیلترها</x-ts-button>
                </div>
            </x-slot:footer>
        </x-ts-slide>
    @endif
</section>
