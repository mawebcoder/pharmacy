{{-- Search box with suggestions (roadmap D21.2 item 6): see App\Modules\Search\Livewire\InstantSearch.
     Product rows show the default SKU's price; the add-to-cart button joins them in S19. --}}
@use('App\Modules\Catalog\Support\ProductCardData')
@php
    $hasText = mb_strlen(trim($value)) >= $minLength;
    $suggestions = $hasText ? $this->suggestions : null;
    $listId = $inputId.'-suggestions';
@endphp

<form
    action="{{ route('search') }}"
    method="get"
    role="search"
    @class(['relative', $class])
    x-data="{
        open: false,
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
    <label for="{{ $inputId }}" class="sr-only">جستجو در محصولات</label>
    <x-ts-icon icon="magnifying-glass" class="pointer-events-none absolute inset-y-0 start-3 my-auto size-5 text-ink-500" />
    <input
        id="{{ $inputId }}"
        x-ref="input"
        name="q"
        type="search"
        autocomplete="off"
        maxlength="120"
        placeholder="جستجوی محصول، برند یا ماده مؤثره"
        wire:model.live.debounce.300ms="value"
        x-on:focus="open = true"
        x-on:input="open = true"
        x-on:keydown.down.prevent="open = true; $nextTick(() => move(1))"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="{{ $listId }}"
        x-bind:aria-expanded="open && {{ $hasText ? 'true' : 'false' }}"
        class="h-11 w-full rounded-full border-0 bg-surface-50 ps-10 pe-4 text-sm text-ink-900 ring-1 ring-line-200 placeholder:text-ink-500 focus:bg-white focus:ring-2 focus:ring-primary-700"
    >

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
            class="absolute inset-x-0 top-full z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-card border border-line-200 bg-white p-2 shadow-card"
        >
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
                        <li wire:key="suggest-product-{{ $product->getId() }}">
                            <a role="option" href="{{ route('products.show', $product) }}" wire:navigate class="flex min-h-14 items-center gap-3 rounded-md px-3 py-1 hover:bg-surface-50 focus:bg-primary-50 focus:outline-none">
                                <span class="size-11 shrink-0 overflow-hidden rounded-md bg-white">
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
