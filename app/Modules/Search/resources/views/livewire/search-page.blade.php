{{-- Search results (roadmap B5, D21.2 item 7): see App\Modules\Search\Livewire\SearchPage. --}}
<div class="space-y-6">
    <header class="space-y-3">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">
            @if ($q === '')
                جستجو
            @else
                نتایج جستجو برای «{{ $q }}»
            @endif
        </h1>

        {{-- Remember this search in the browser's recent searches (latest 6; owner, 2026-11-14). --}}
        @if ($q !== '' && $available)
            <span hidden x-data x-init="$store.searchHistory.add(@js($q))"></span>
        @endif

        {{-- No box of its own: the header's box holds the query (owner, 2026-11-15: one search bar). Opened without a
             query (the «جستجو» tab), the cursor goes straight into that box. --}}
        @if ($q === '')
            <span hidden x-data x-init="$nextTick(() => document.getElementById(window.matchMedia('(min-width: 768px)').matches ? 'site-search' : 'site-search-mobile')?.focus())"></span>
        @endif
    </header>

    @if ($q !== '' && ! $available)
        <div role="status" class="rounded-card bg-warning-500/10 px-4 py-6 text-center text-ink-900">
            جستجو موقتاً در دسترس نیست. لطفاً چند دقیقه دیگر دوباره امتحان کنید یا از دسته‌بندی‌ها استفاده کنید.
        </div>
    @elseif ($q !== '' && $total > 0)
        <livewire:catalog::product-listing
            :query="$q"
            product-route="search.open"
            :product-route-parameters="['searchLog' => $searchLogId]"
            :key="'search-'.md5($q)"
        />
    @elseif ($q !== '')
        {{-- Like the empty cart and an empty list (owner, 2026-11-15): one clear way on. --}}
        <div class="rounded-card border border-line-200 bg-card px-6 py-12 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary-50 text-primary-700">
                <x-ts-icon icon="magnifying-glass" class="size-7" />
            </span>
            <p class="mt-4 font-bold text-ink-900">کالایی برای «{{ $q }}» پیدا نشد</p>
            <p class="mt-1 text-sm text-ink-500">املای کلمه را بررسی کنید، از کلمه‌های کوتاه‌تر استفاده کنید یا از پیشنهادهای زیر کمک بگیرید.</p>
            <a href="{{ route('categories.index') }}" wire:navigate class="mt-5 inline-flex h-12 items-center justify-center gap-2 rounded-card bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                <x-ts-icon icon="squares-2x2" class="size-5" />
                مشاهده همه دسته‌بندی‌ها
            </a>
        </div>
    @endif

    @if ($q === '' || $total === 0)
        <section aria-labelledby="search-suggestions" class="space-y-4">
            <h2 id="search-suggestions" class="text-lg font-bold text-ink-900">پیشنهاد ما</h2>
            <x-catalog::tag-chips label="جستجوهای پرطرفدار" />
            @if ($categories !== [])
                <ul class="flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ $category->url() }}" wire:navigate class="flex h-11 items-center rounded-full border border-line-200 bg-card px-4 text-sm text-ink-900 hover:border-primary-300 hover:text-primary-700">{{ $category->nameFa }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
