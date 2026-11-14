{{-- Header cart (roadmap B9.1): see App\Modules\Cart\Livewire\MiniCart. Phones and tablets get a plain link. --}}
@use('App\Modules\Shared\Support\PersianDigits')

<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <a href="{{ route('cart') }}" wire:navigate class="relative flex h-11 items-center gap-2 rounded-full px-3 text-ink-900 hover:bg-surface-50 xl:hidden" aria-label="سبد خرید">
        <x-ts-icon icon="shopping-bag" class="size-6" />
        <x-cart::count-badge class="!end-1 !top-0" />
    </a>

    <button
        type="button"
        class="relative hidden h-11 items-center gap-2 rounded-full px-3 text-ink-900 hover:bg-surface-50 xl:flex"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open"
        x-on:click="open = ! open; if (open && ! $wire.loaded) $wire.load()"
    >
        <span class="relative">
            <x-ts-icon icon="shopping-bag" class="size-6" />
            <x-cart::count-badge />
        </span>
        <span class="text-sm" x-data x-text="$store.cart.count > 0 ? new Intl.NumberFormat('fa-IR').format(Math.floor($store.cart.total / 10)) + ' تومان' : 'سبد خرید'">سبد خرید</span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top
        role="dialog"
        aria-label="سبد خرید"
        class="absolute end-0 top-full z-50 mt-2 hidden w-96 rounded-card border border-line-200 bg-white shadow-lg xl:block"
    >
        @if ($summary === null)
            <p class="p-6 text-center text-sm text-ink-500">در حال بارگذاری…</p>
        @elseif ($summary->isEmpty())
            <p class="p-6 text-center text-sm text-ink-500">سبد خرید شما خالی است.</p>
        @else
            <ul class="max-h-80 divide-y divide-line-200 overflow-y-auto">
                @foreach ($summary->lines as $line)
                    <li wire:key="mini-line-{{ $line->variant->variantId }}" @class(['flex items-center gap-3 p-3', 'opacity-60' => ! $line->isAvailable()])>
                        <span class="size-14 shrink-0 overflow-hidden rounded-md bg-white">
                            <x-media::picture :media="$line->variant->image" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $line->variant->productSlug) }}" wire:navigate class="line-clamp-2 text-sm text-ink-900 hover:text-primary-700">{{ $line->variant->productName }}</a>
                            @if ($line->variant->hasOtherVariants)
                                <span class="block text-xs text-ink-500">{{ $line->variant->variantTitle }}</span>
                            @endif
                            <span class="block text-xs text-ink-500">{{ PersianDigits::toPersian($line->quantity) }} × @toman($line->variant->price)</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-3 border-t border-line-200 p-4">
                <p class="flex justify-between text-sm"><span class="text-ink-500">جمع سبد</span><span class="font-bold text-ink-900">@toman($summary->subtotal)</span></p>
                <a href="{{ route('cart') }}" wire:navigate class="flex h-11 items-center justify-center rounded-card bg-primary-700 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">مشاهده سبد و ادامه</a>
            </div>
        @endif
    </div>
</div>
