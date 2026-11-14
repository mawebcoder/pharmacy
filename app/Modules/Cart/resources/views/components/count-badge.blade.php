{{-- The cart's item count on the bag icon (roadmap B9.1, S19), from the Alpine «cart» store; hidden at zero. --}}
<span
    x-data
    x-show="$store.cart.count > 0"
    x-cloak
    x-text="new Intl.NumberFormat('fa-IR').format($store.cart.count)"
    {{ $attributes->class('absolute -top-1 -end-1 grid h-5 min-w-5 place-items-center rounded-full bg-accent-600 px-1 text-[11px] leading-none font-bold text-white') }}
    aria-hidden="true"
></span>
