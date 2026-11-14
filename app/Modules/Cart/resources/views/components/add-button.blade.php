{{--
    Add to cart (roadmap S19): «+» until the SKU is in the cart, then a «− n +» stepper (the minus becomes a bin at 1).
    State comes from the Alpine «cart» store; changes go to the CartState component.
    <x-cart::add-button :variant-id="$id" :label="$product->getNameFa()" />   size="sm" for search rows; wide for full width;
    stack: the stepper takes a full line of its own (in a flex-wrap row), so a narrow card keeps the price above it.
--}}
@props(['variantId', 'label' => '', 'size' => 'md', 'wide' => false, 'stack' => false])

@php
    $box = $size === 'sm' ? 'size-9' : 'size-11';
@endphp

<div
    x-data="{ id: {{ (int) $variantId }}, get quantity() { return $store.cart.quantityOf(this.id) } }"
    @if ($stack) x-bind:class="quantity > 0 && 'basis-full'" @endif
    {{ $attributes->class(['relative z-10 shrink-0', 'w-full' => $wide]) }}
>
    <button
        type="button"
        x-show="quantity === 0"
        x-on:click.prevent.stop="$dispatch('cart-add', { variantId: id })"
        @class([
            'grid place-items-center rounded-full bg-primary-700 text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700',
            $box => ! $wide,
            'h-11 w-full gap-2 !flex items-center justify-center rounded-card px-4 text-sm font-bold' => $wide,
        ])
        aria-label="افزودن {{ $label }} به سبد خرید"
    >
        <x-ts-icon icon="plus" @class(['size-5' => $size !== 'sm', 'size-4' => $size === 'sm']) />
        @if ($wide)
            افزودن به سبد
        @endif
    </button>

    <div
        x-show="quantity > 0"
        x-cloak
        @class(['flex items-center justify-between gap-1 rounded-full border border-primary-700 bg-white text-primary-700', 'w-full rounded-card' => $wide || $stack])
        role="group"
        aria-label="تعداد {{ $label }} در سبد"
    >
        <button type="button" x-on:click.prevent.stop="$dispatch('cart-set', { variantId: id, quantity: quantity - 1 })" class="grid {{ $box }} place-items-center rounded-full hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700" x-bind:aria-label="quantity === 1 ? 'حذف از سبد' : 'کم کردن یکی'">
            <x-ts-icon icon="trash" class="size-4" x-show="quantity === 1" />
            <x-ts-icon icon="minus" class="size-4" x-show="quantity > 1" x-cloak />
        </button>
        <span class="min-w-6 text-center text-sm font-bold tabular-nums" x-text="new Intl.NumberFormat('fa-IR').format(quantity)" aria-live="polite"></span>
        <button
            type="button"
            x-on:click.prevent.stop="$dispatch('cart-add', { variantId: id })"
            x-bind:disabled="$store.cart.atLimit(id)"
            x-bind:title="$store.cart.atLimit(id) ? 'حداکثر تعداد مجاز در هر سفارش' : null"
            class="grid {{ $box }} place-items-center rounded-full hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:text-ink-500 disabled:opacity-50 disabled:hover:bg-transparent"
            x-bind:aria-label="$store.cart.atLimit(id) ? 'به حداکثر تعداد مجاز رسیده‌اید' : 'افزودن یکی'"
        >
            <x-ts-icon icon="plus" class="size-4" />
        </button>
    </div>
</div>
