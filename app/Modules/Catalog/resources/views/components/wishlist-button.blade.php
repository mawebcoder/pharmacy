{{--
    Heart button (roadmap S18): saves or removes a product from the wishlist; its state comes from the Alpine
    «wishlist» store. Guests are asked to sign in first. <x-catalog::wishlist-button :product-id="$product->getId()" />
--}}
@props(['productId'])

<button
    type="button"
    x-data="{ id: {{ (int) $productId }} }"
    x-on:click.prevent.stop="$dispatch('wishlist-toggle', { productId: id })"
    x-bind:aria-pressed="$store.wishlist.has(id) ? 'true' : 'false'"
    x-bind:aria-label="$store.wishlist.has(id) ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'"
    aria-label="افزودن به علاقه‌مندی‌ها"
    aria-pressed="false"
    {{ $attributes->class('z-10 grid size-11 place-items-center rounded-full bg-white/90 text-ink-500 shadow-sm hover:text-accent-600 focus-visible:outline-2 focus-visible:outline-primary-700') }}
>
    <x-heroicon-o-heart class="size-5" x-show="! $store.wishlist.has(id)" aria-hidden="true" />
    <x-heroicon-s-heart class="size-5 text-accent-600" x-show="$store.wishlist.has(id)" x-cloak aria-hidden="true" />
</button>
