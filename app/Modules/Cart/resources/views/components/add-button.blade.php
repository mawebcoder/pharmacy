{{--
    Add to cart (roadmap S19): «+» until the SKU is in the cart, then a «− n +» stepper (the minus becomes a bin at 1).
    State comes from the Alpine «cart» store; changes go to the CartState component. While a change is on its way the
    button keeps its size and shows a spinner in place of its icon (or of the quantity in the stepper) and ignores
    clicks, until CartState answers with «cart-settled» (owner, 2026-11-14).
    <x-cart::add-button :variant-id="$id" :label="$product->getNameFa()" />   size="sm" for search rows; wide for full width;
    stack: the stepper takes a full line of its own (in a flex-wrap row), so a narrow card keeps the price above it.
--}}
@props(['variantId', 'label' => '', 'size' => 'md', 'wide' => false, 'stack' => false])

@php
    $box = $size === 'sm' ? 'size-9' : 'size-11';
    $icon = $size === 'sm' ? 'size-4' : 'size-5';
@endphp

<div
    x-data="{
        id: {{ (int) $variantId }},
        busy: false,
        timer: null,
        get quantity() { return $store.cart.quantityOf(this.id) },
        send(event, detail) {
            if (this.busy) return;
            this.busy = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.busy = false), 10000);
            $dispatch(event, { variantId: this.id, ...detail });
        },
        settle() {
            this.busy = false;
            clearTimeout(this.timer);
        },
    }"
    x-on:cart-settled.window="$event.detail.variantId === id && settle()"
    x-bind:aria-busy="busy"
    @if ($stack) x-bind:class="quantity > 0 && 'basis-full'" @endif
    {{ $attributes->class(['relative z-10 shrink-0', 'w-full' => $wide]) }}
>
    <button
        type="button"
        x-show="quantity === 0"
        x-on:click.prevent.stop="send('cart-add', {})"
        x-bind:disabled="busy"
        @class([
            'place-items-center rounded-full bg-primary-700 text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-wait disabled:opacity-80',
            'grid '.$box => ! $wide,
            'flex h-11 w-full items-center justify-center gap-2 rounded-card px-4 text-sm font-bold' => $wide,
        ])
        aria-label="افزودن {{ $label }} به سبد خرید"
    >
        <x-ts-icon icon="plus" class="{{ $icon }}" x-show="! busy" />
        <x-ui.spinner class="{{ $icon }}" x-show="busy" x-cloak />
        @if ($wide)
            افزودن به سبد
        @endif
    </button>

    <div
        x-show="quantity > 0"
        x-cloak
        @class(['flex items-center justify-between gap-1 rounded-full border border-primary-700 bg-card text-primary-700', 'w-full rounded-card' => $wide || $stack])
        role="group"
        aria-label="تعداد {{ $label }} در سبد"
    >
        <button type="button" x-on:click.prevent.stop="send('cart-set', { quantity: quantity - 1 })" x-bind:disabled="busy" class="grid {{ $box }} place-items-center rounded-full hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-wait" x-bind:aria-label="quantity === 1 ? 'حذف از سبد' : 'کم کردن یکی'">
            <x-ts-icon icon="trash" class="size-4" x-show="quantity === 1" />
            <x-ts-icon icon="minus" class="size-4" x-show="quantity > 1" x-cloak />
        </button>
        <span class="grid min-w-6 place-items-center text-center text-sm font-bold tabular-nums" aria-live="polite">
            <span x-show="! busy" x-text="new Intl.NumberFormat('fa-IR').format(quantity)"></span>
            <x-ui.spinner class="size-4" x-show="busy" x-cloak />
        </span>
        <button
            type="button"
            x-on:click.prevent.stop="send('cart-add', {})"
            x-bind:disabled="busy || $store.cart.atLimit(id)"
            x-bind:title="$store.cart.atLimit(id) ? 'حداکثر تعداد مجاز در هر سفارش' : null"
            class="grid {{ $box }} place-items-center rounded-full hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:text-ink-500 disabled:opacity-50 disabled:hover:bg-transparent"
            x-bind:aria-label="$store.cart.atLimit(id) ? 'به حداکثر تعداد مجاز رسیده‌اید' : 'افزودن یکی'"
        >
            <x-ts-icon icon="plus" class="size-4" />
        </button>
    </div>
</div>
