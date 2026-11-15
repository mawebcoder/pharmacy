{{--
    The countdown before a product leaves the cart (owner, 2026-11-15: small, round and soft): a light pill with a ring
    that empties around the seconds left, and «انصراف» ($store.cartRemoval; the length is set in «تنظیمات فروشگاه»).
    Shown only while the SKU's removal is pending, in the place of the control that started it; size it with classes.
    <x-cart::removal-countdown :variant-id="$id" :label="$name" class="h-9" />
--}}
@props(['variantId', 'label' => ''])

<div
    x-data="{ removalOf: {{ (int) $variantId }} }"
    x-show="$store.cartRemoval.isPending(removalOf)"
    x-cloak
    role="status"
    {{ $attributes->class('inline-flex items-center justify-center gap-2 rounded-full bg-danger-600/10 ps-1.5 pe-1 text-danger-600') }}
>
    <span class="sr-only">{{ $label }} از سبد خرید حذف می‌شود؛ برای نگه‌داشتن، «انصراف» را بزنید.</span>
    <span class="relative grid size-6 shrink-0 place-items-center" aria-hidden="true">
        <svg viewBox="0 0 24 24" class="absolute inset-0 size-6 -rotate-90">
            <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" class="opacity-20" />
            <circle
                cx="12"
                cy="12"
                r="10"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-dasharray="62.83"
                x-bind:style="$store.cartRemoval.isPending(removalOf) && `animation: cart-removal-ring ${$store.cartRemoval.durationOf(removalOf)}s linear forwards`"
            />
        </svg>
        <span class="text-[11px] font-bold tabular-nums" x-text="new Intl.NumberFormat('fa-IR').format($store.cartRemoval.secondsLeft(removalOf))"></span>
    </span>
    <button type="button" x-on:click.prevent.stop="$store.cartRemoval.cancel(removalOf)" class="flex h-full min-w-11 items-center justify-center rounded-full px-2 text-xs font-bold hover:bg-danger-600/10 focus-visible:outline-2 focus-visible:outline-primary-700">انصراف</button>
</div>
