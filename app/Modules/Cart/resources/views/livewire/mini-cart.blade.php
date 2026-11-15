{{-- Header cart (roadmap B9.1): see App\Modules\Cart\Livewire\MiniCart. Phones and tablets get a plain link. Each line has the
     «− n +» stepper and a bin, and «حذف همه» empties the cart (owner, 2026-11-15). --}}

{{-- Clicks and Escape on the confirmation dialog do not close the drop-down. While it is open the page below the header
     is dimmed and blurred (owner, 2026-11-15). --}}
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="$store.confirm.active || (open = false)" x-on:click.outside="$store.confirm.active || (open = false)">
    <x-layout.header-backdrop show="open" close="open = false" class="hidden xl:block" />

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
        {{-- A link closes the drop-down at once; the page shows its own skeleton while it loads (owner, 2026-11-15). --}}
        x-on:click="$event.target.closest('a[href]') && (open = false)"
        role="dialog"
        aria-label="سبد خرید"
        class="absolute end-0 top-full z-50 mt-2 hidden w-96 rounded-card border border-line-200 bg-card shadow-lg xl:block"
    >
        @if ($summary === null)
            <p class="p-6 text-center text-sm text-ink-500">در حال بارگذاری…</p>
        @elseif ($summary->isEmpty())
            <p class="p-6 text-center text-sm text-ink-500" x-init="$store.cartRemoval.stopClearing()">سبد خرید شما خالی است.</p>
        @else
            <div x-show="$store.cartRemoval.clearing" x-cloak x-transition.opacity class="absolute inset-0 z-10 grid place-items-center rounded-card bg-card/60 backdrop-blur-sm" role="status" aria-label="در حال خالی کردن سبد خرید">
                <x-ui.spinner class="size-8 text-primary-700" />
            </div>
            <ul class="max-h-80 divide-y divide-line-200 overflow-y-auto">
                @foreach ($summary->lines as $line)
                    <li wire:key="mini-line-{{ $line->variant->variantId }}" x-show="! $store.cartRemoval.isRemoved({{ $line->variant->variantId }})" @class(['flex items-center gap-3 p-3', 'opacity-60' => ! $line->isAvailable()])>
                        <span class="size-14 shrink-0 overflow-hidden rounded-md bg-media">
                            <x-media::picture :media="$line->variant->image" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $line->variant->productSlug) }}" wire:navigate class="line-clamp-2 text-sm text-ink-900 hover:text-primary-700">{{ $line->variant->productName }}</a>
                            @if ($line->variant->hasOtherVariants)
                                <span class="block text-xs text-ink-500">{{ $line->variant->variantTitle }}</span>
                            @endif
                            @if ($line->isAvailable())
                                {{-- Change the quantity here too, or remove the line whatever its quantity (owner, 2026-11-15). --}}
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <x-cart::add-button :variant-id="$line->variant->variantId" :label="$line->variant->productName" size="sm" />
                                    <span class="text-sm font-bold text-ink-900">@toman($line->lineTotal)</span>
                                </div>
                            @else
                                <span class="block text-xs text-danger-600">{{ $line->variant->refusalMessage() }}</span>
                            @endif
                        </div>
                        <button type="button" x-show="! $store.cartRemoval.isPending({{ $line->variant->variantId }})" x-on:click="$store.cartRemoval.start({{ $line->variant->variantId }})" class="grid size-11 shrink-0 place-items-center self-start rounded-card text-ink-500 hover:bg-surface-50 hover:text-danger-600 focus-visible:outline-2 focus-visible:outline-primary-700" aria-label="حذف {{ $line->variant->productName }} از سبد">
                            <x-ts-icon icon="trash" class="size-5" />
                        </button>
                        @unless ($line->isAvailable())
                            <x-cart::removal-countdown :variant-id="$line->variant->variantId" :label="$line->variant->productName" class="h-9 shrink-0 self-start" />
                        @endunless
                    </li>
                @endforeach
            </ul>
            <div class="space-y-3 border-t border-line-200 p-4">
                <div class="flex items-center justify-between text-sm">
                    <span><span class="text-ink-500">جمع سبد</span> <span class="font-bold text-ink-900">@toman($summary->subtotal)</span></span>
                    <button type="button" x-on:click="$store.confirm.ask({ title: 'خالی کردن سبد خرید', message: 'همه کالاها از سبد خرید حذف شوند؟', confirm: 'حذف همه' }).then((ok) => ok && $store.cartRemoval.clearAll())" class="-me-2 inline-flex min-h-11 items-center gap-1 rounded-card px-2 text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="trash" class="size-4" />
                        حذف همه
                    </button>
                </div>
                <a href="{{ route('cart') }}" wire:navigate class="flex h-11 items-center justify-center rounded-card bg-primary-700 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">مشاهده سبد و ادامه</a>
            </div>
        @endif
    </div>
</div>
