{{--
    Product card (roadmap B9.4): square photo on white with the colour placeholder, at most one badge, brand, name
    (2 lines), price (old price struck through when on sale). The whole card links to the product; the quick view
    button opens the product's summary without leaving the list. Add-to-cart arrives with the cart (S19).
    <x-catalog::product-card :product="$product" />  (product with brand, media and active variants loaded)
    :href overrides the product link, e.g. to record clicks from search results.
--}}
@props(['product', 'quickView' => true, 'href' => null])

@use('App\Modules\Catalog\Support\ProductCardData')

@php
    $card = ProductCardData::for($product);
    $badgeClasses = [
        'near-expiry' => 'bg-warning-500 text-ink-900',
        'damaged-box' => 'bg-line-200 text-ink-900',
        'new' => 'bg-ink-900 text-white',
    ];
@endphp

<article {{ $attributes->class('group relative flex h-full flex-col overflow-hidden rounded-card border border-line-200 bg-white transition duration-200 ease-out hover:-translate-y-1 hover:border-primary-200 hover:shadow-card-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0') }}>
    <div class="relative aspect-square bg-white">
        @if ($product->getMainImage())
            <x-media::picture :media="$product->getMainImage()" slot-name="product-card" :alt="$product->getNameFa()" class="size-full object-contain" />
        @else
            <div class="grid size-full place-items-center bg-surface-50 text-ink-500" aria-hidden="true">
                <x-ts-icon icon="photo" class="size-10" />
            </div>
        @endif

        @if ($card->badge)
            <span @class(['absolute start-2 top-2 rounded-full px-2 text-xs leading-6 font-bold', $badgeClasses[$card->badgeTone] ?? ''])>{{ $card->badge }}</span>
        @endif

        <x-catalog::wishlist-button :product-id="$product->getId()" class="absolute end-2 top-2" />

        @if ($quickView)
            <button
                type="button"
                wire:click="$dispatch('quick-view', { productId: {{ $product->getId() }} })"
                class="absolute end-2 bottom-2 z-10 grid size-11 place-items-center rounded-full bg-white/90 text-ink-500 shadow-sm hover:bg-primary-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700"
                aria-label="نمایش سریع {{ $product->getNameFa() }}"
            >
                <x-ts-icon icon="eye" class="size-5" />
            </button>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3 md:p-4">
        <p class="truncate text-xs text-ink-500">{{ $product->getBrand()?->getNameFa() }}</p>
        <h3 class="line-clamp-2 min-h-12 text-sm leading-6 text-ink-900 md:text-base">
            <a href="{{ $href ?? route('products.show', $product) }}" @unless ($href) wire:navigate @endunless class="after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:rounded-card after:focus-visible:outline-2 after:focus-visible:outline-primary-700">
                {{ $product->getNameFa() }}
            </a>
        </h3>

        {{-- Same layout with or without a haraj: a two-line price area (old price + badge, then the price), then the
             add-to-cart line, so neighbouring cards line up. --}}
        <div class="mt-auto space-y-2 pt-2">
            <div class="flex min-h-12 flex-col justify-end">
                @if ($card->price !== null && $card->inStock)
                    <x-ui.price :price="$card->price" :old="$card->oldPrice" />
                @else
                    <span class="text-sm text-ink-500">ناموجود</span>
                @endif
            </div>
            @if ($card->price !== null && $card->inStock && $card->variantId !== null && $product->isSellableType() && ! $product->requiresPrescription())
                <div class="flex justify-end">
                    <x-cart::add-button :variant-id="$card->variantId" :label="$product->getNameFa()" stack />
                </div>
            @endif
        </div>
    </div>
</article>
