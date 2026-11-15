{{--
    Add to cart (roadmap S19): a small light square «+» (owner, 2026-11-14), or a full-width button when wide, until the SKU is in the cart, then a «− n +» stepper (the minus becomes a bin at 1).
    State comes from the Alpine «cart» store; changes go to the CartState component. While a change is on its way the
    button keeps its size and shows a spinner in place of its icon (or of the quantity in the stepper) and ignores
    clicks, until CartState answers with «cart-settled» (owner, 2026-11-14). The bin at 1 (and a cart line's own bin)
    starts the removal countdown of $store.cartRemoval instead: a small pill in the stepper's place, until the product
    leaves the cart (owner, 2026-11-15).
    <x-cart::add-button :variant-id="$id" :label="$product->getNameFa()" />   size="sm" for search rows; wide for full width;
    card: a full-width row of fixed height on product cards (owner, 2026-11-15: the card must not grow on add), the «+»
    at its end, then a light bar «− | n | +» with thin separators, always fully shown.
    :countdown="false" leaves the removal countdown to the caller.
--}}
@props(['variantId', 'label' => '', 'size' => 'md', 'wide' => false, 'card' => false, 'countdown' => true])

@php
    $box = $size === 'sm' ? 'size-9' : 'size-11';
    $icon = $size === 'sm' ? 'size-4' : 'size-5';
    // The countdown pill sits where the stepper was, as tall as it.
    $countdownSize = $size === 'sm' ? 'h-9' : 'h-11';
@endphp

<div
    x-data="{
        id: {{ (int) $variantId }},
        busy: false,
        timer: null,
        get quantity() { return $store.cart.quantityOf(this.id) },
        get removing() { return $store.cartRemoval.isPending(this.id) },
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
    {{ $attributes->class(['relative z-10 shrink-0', 'w-full' => $wide, 'flex h-11 w-full justify-end' => $card]) }}
>
    <button
        type="button"
        x-show="quantity === 0"
        x-on:click.prevent.stop="send('cart-add', {})"
        x-bind:disabled="busy"
        @class([
            'place-items-center focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-wait disabled:opacity-80',
            'grid rounded-lg bg-line-200/50 text-ink-900 hover:bg-primary-50 hover:text-primary-700 '.$box => ! $wide,
            'flex h-11 w-full items-center justify-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800' => $wide,
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
        x-show="quantity > 0 && ! removing"
        x-cloak
        @class([
            'flex items-center justify-between gap-1',
            'rounded-full border border-primary-700 bg-card text-primary-700' => ! $card,
            'w-full rounded-card' => $wide,
            'w-full rounded-lg bg-line-200/50 text-ink-900' => $card,
        ])
        role="group"
        aria-label="تعداد {{ $label }} در سبد"
    >
        <button type="button" x-on:click.prevent.stop="quantity > 1 ? send('cart-set', { quantity: quantity - 1 }) : $store.cartRemoval.start(id)" x-bind:disabled="busy" class="grid {{ $box }} place-items-center rounded-full transition-opacity hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-wait motion-reduce:transition-none" x-bind:aria-label="quantity === 1 ? 'حذف از سبد' : 'کم کردن یکی'">
            <x-ts-icon icon="trash" class="size-4" x-show="quantity === 1" />
            <x-ts-icon icon="minus" class="size-4" x-show="quantity > 1" x-cloak />
        </button>
        @if ($card)
            {{-- The number between two thin separators. --}}
            <span class="flex flex-1 items-center justify-center gap-4">
                <span class="h-5 w-px bg-line-300" aria-hidden="true"></span>
        @endif
        <span class="grid min-w-6 place-items-center text-center text-sm font-bold tabular-nums" aria-live="polite">
            <span x-show="! busy" x-text="new Intl.NumberFormat('fa-IR').format(quantity)"></span>
            <x-ui.spinner class="size-4" x-show="busy" x-cloak />
        </span>
        @if ($card)
                <span class="h-5 w-px bg-line-300" aria-hidden="true"></span>
            </span>
        @endif
        <button
            type="button"
            x-on:click.prevent.stop="send('cart-add', {})"
            x-bind:disabled="busy || $store.cart.atLimit(id)"
            x-bind:title="$store.cart.atLimit(id) ? 'حداکثر تعداد مجاز در هر سفارش' : null"
           
            class="grid {{ $box }} place-items-center rounded-full transition-opacity hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:text-ink-500 disabled:opacity-50 disabled:hover:bg-transparent motion-reduce:transition-none"
            x-bind:aria-label="$store.cart.atLimit(id) ? 'به حداکثر تعداد مجاز رسیده‌اید' : 'افزودن یکی'"
        >
            <x-ts-icon icon="plus" class="size-4" />
        </button>
    </div>

    {{-- The removal countdown in the stepper's place (owner, 2026-11-15). --}}
    @if ($countdown)
        <x-cart::removal-countdown :variant-id="$variantId" :label="$label" :class="$countdownSize" />
    @endif
</div>
