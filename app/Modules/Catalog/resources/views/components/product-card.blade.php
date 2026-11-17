{{--
    Product card (roadmap B9.4), shaped like the owner's reference shop (owner, 2026-11-14/15: about 230 × 310 px): a
    photo contained on a white well inset from the card's edges, one corner badge («زودمصرف» always when flagged, with a
    strip saying why it is discounted), then the name (2 lines), the price
    (old price and discount on a second line when on sale) and the cart row (the square «+», then the quantity bar).
    Every part has a fixed height, so a card is as tall on the home page as in a list or after an add (owner,
    2026-11-15), whatever its width or sale. No brand line. The whole card links to the
    product; only the heart and the cart row act on their own (owner, 2026-11-15: no quick view on cards).
    <x-catalog::product-card :product="$product" />  (product with media and active variants loaded: ProductListing::CARD_RELATIONS)
    :href overrides the product link, e.g. to record clicks from search results.
--}}
@props(['product', 'href' => null])

@use('App\Modules\Catalog\Support\ProductCardData')

@php
    $card = ProductCardData::for($product);
    $badgeClasses = [
        'near-expiry' => 'bg-warning-500 text-ink-900',
        'new' => 'bg-ink-900 text-card',
    ];
@endphp

<article {{ $attributes->class('group relative flex h-full flex-col overflow-hidden rounded-card border border-line-200 bg-card transition-colors duration-200 ease-out hover:border-primary-700 motion-reduce:transition-none') }}>
    <div class="relative mx-2.5 mt-2.5 h-24 overflow-hidden rounded-lg bg-media md:mx-3 md:mt-3 md:h-32">
        @if ($product->getMainImage())
            <x-media::picture :media="$product->getMainImage()" slot-name="product-card" :alt="$product->getNameFa()" class="size-full object-contain p-2" />
        @else
            <div class="grid size-full place-items-center bg-surface-50 text-ink-500" aria-hidden="true">
                <x-ts-icon icon="photo" class="size-10" />
            </div>
        @endif

        @if ($card->badge)
            <span @class(['absolute start-2 top-2 rounded-full px-2 text-xs leading-6 font-bold', $badgeClasses[$card->badgeTone] ?? ''])>{{ $card->badge }}</span>
        @endif

        {{-- Why it is discounted (owner, 2026-11-17), inside the photo well so every card keeps the same height. --}}
        @if ($card->nearExpiry)
            <p class="absolute inset-x-0 bottom-0 bg-warning-500/90 px-2 text-center text-[11px] leading-5 font-medium text-ink-900">انقضای نزدیک؛ دلیل تخفیف</p>
        @endif

        <x-catalog::wishlist-button :product-id="$product->getId()" class="absolute end-2 top-2" />
    </div>

    <div class="flex flex-1 flex-col gap-0.5 p-2.5 md:p-3">
        <h3 class="line-clamp-2 h-10 text-sm leading-5 text-ink-900">
            <a href="{{ $href ?? route('products.show', $product) }}" @unless ($href) wire:navigate @endunless class="after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:rounded-card after:focus-visible:outline-2 after:focus-visible:outline-primary-700">
                {{ $product->getNameFa() }}
            </a>
        </h3>

        {{-- Same height with or without a haraj or the cart row: the two-line price area, then the 44 px cart row. --}}
        <div class="mt-auto space-y-2 pt-2">
            <div class="h-11">
                @if ($card->price !== null && $card->inStock)
                    <x-ui.price :price="$card->price" :old="$card->oldPrice" size="card" />
                @else
                    <span class="text-sm text-ink-500">ناموجود</span>
                @endif
            </div>
            @if ($card->price !== null && $card->inStock && $card->variantId !== null && $product->isSellableType() && ! $product->requiresPrescription())
                <x-cart::add-button :variant-id="$card->variantId" :label="$product->getNameFa()" card />
            @else
                <div class="h-11" aria-hidden="true"></div>
            @endif
        </div>
    </div>
</article>
