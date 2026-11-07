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

        {{-- The header box is hidden on phones below the header; this one is always here. --}}
        <livewire:search::instant-search input-id="search-page-box" :value="$q" class="max-w-xl" />
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
        <div class="rounded-card border border-line-200 bg-white px-6 py-10 text-center">
            <x-ts-icon icon="magnifying-glass" class="mx-auto size-10 text-ink-500" />
            <p class="mt-3 font-bold text-ink-900">کالایی برای «{{ $q }}» پیدا نشد.</p>
            <p class="mt-1 text-sm text-ink-500">املای کلمه را بررسی کنید، از کلمه‌های کوتاه‌تر استفاده کنید یا از پیشنهادهای زیر کمک بگیرید.</p>
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
                            <a href="{{ $category->url() }}" wire:navigate class="flex h-11 items-center rounded-full border border-line-200 bg-white px-4 text-sm text-ink-900 hover:border-primary-300 hover:text-primary-700">{{ $category->nameFa }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
