{{-- «سبد خرید» (roadmap B9.6): see App\Modules\Cart\Livewire\CartPage. --}}
@use('App\Modules\Shared\Support\PersianDigits')
@use('App\Modules\Shared\Support\Toman')

<div class="space-y-4" x-bind:aria-busy="$store.cartRemoval.clearing">
    {{-- «حذف همه» blurs the whole page below the header, its own bars included, until the cart re-renders empty
         (owner, 2026-11-15). z-35 like the page skeleton: above the page's fixed bars (z-30), below the header and
         bottom nav (z-40). --}}
    <div
        x-data="{ below: 0 }"
        x-effect="if ($store.cartRemoval.clearing) below = document.querySelector('header')?.getBoundingClientRect().bottom ?? 0"
        x-show="$store.cartRemoval.clearing"
        x-cloak
        x-transition.opacity
        x-bind:style="{ top: below + 'px' }"
        class="fixed inset-x-0 bottom-0 z-35 grid place-items-center bg-surface-50/60 backdrop-blur-sm [contain:strict]"
        role="status"
        aria-label="در حال خالی کردن سبد خرید"
    >
        <x-ui.spinner class="size-8 text-primary-700" />
    </div>

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-ink-900 xl:text-2xl">سبد خرید</h1>
        @unless ($summary->isEmpty())
            <button type="button" x-on:click="$store.confirm.ask({ title: 'خالی کردن سبد خرید', message: 'همه کالاها از سبد خرید حذف شوند؟', confirm: 'حذف همه' }).then((ok) => ok && $store.cartRemoval.clearAll())" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                <x-ts-icon icon="trash" class="size-4" />
                حذف همه
            </button>
        @endunless
    </div>

    @if (session('reorder.message'))
        <p role="status" class="flex items-start gap-2 rounded-card bg-primary-50 px-4 py-3 text-sm text-primary-800">
            <x-ts-icon icon="information-circle" class="mt-0.5 size-5 shrink-0" />
            {{ session('reorder.message') }}
        </p>
    @endif

    @if ($summary->isEmpty())
        {{-- The emptied cart ends the «حذف همه» blur. --}}
        <div class="rounded-card border border-line-200 bg-card px-6 py-12 text-center" x-data x-init="$store.cartRemoval.stopClearing()">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary-50 text-primary-700">
                <x-ts-icon icon="shopping-bag" class="size-7" />
            </span>
            <p class="mt-4 font-bold text-ink-900">سبد خرید شما خالی است</p>
            <p class="mt-1 text-sm text-ink-500">از دسته‌بندی‌های پرطرفدار شروع کنید:</p>
            @if ($popularCategories !== [])
                <ul class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach ($popularCategories as $category)
                        <li><a href="{{ $category->url() }}" wire:navigate class="inline-flex min-h-11 items-center rounded-full border border-line-200 px-4 text-sm text-ink-900 hover:border-primary-700 hover:text-primary-700">{{ $category->nameFa }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div class="grid gap-4 xl:grid-cols-12 xl:items-start xl:gap-6">
            <section class="space-y-3 xl:col-span-8" aria-label="کالاهای سبد">
                @if ($summary->hasUnavailable())
                    <div class="flex flex-col gap-3 rounded-card border border-danger-600 bg-card p-4 md:flex-row md:items-center md:justify-between" role="alert">
                        <p class="flex items-start gap-2 text-sm leading-7 text-ink-900">
                            <x-ts-icon icon="exclamation-triangle" class="mt-1 size-5 shrink-0 text-danger-600" />
                            برخی کالاهای سبد شما دیگر قابل فروش نیستند. برای ادامه، سبد را به‌روزرسانی کنید تا حذف شوند.
                        </p>
                        <button type="button" wire:click="removeUnavailable" wire:loading.attr="disabled" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-card bg-danger-600 px-4 text-sm font-bold text-white hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600">
                            <x-ts-icon icon="arrow-path" class="size-4" />
                            به‌روزرسانی سبد
                        </button>
                    </div>
                @endif

                <ul class="divide-y divide-line-200 rounded-card border border-line-200 bg-card">
                    @foreach ($summary->lines as $line)
                        @php($variant = $line->variant)
                        @php($before = $priceChanges[$variant->variantId] ?? null)
                        <li wire:key="cart-line-{{ $variant->variantId }}" x-show="! $store.cartRemoval.isRemoved({{ $variant->variantId }})" class="p-4">
                            <div @class(['flex gap-3', 'opacity-60' => ! $line->isAvailable()])>
                                <a href="{{ route('products.show', $variant->productSlug) }}" wire:navigate class="size-16 shrink-0 overflow-hidden rounded-md border border-line-200 bg-media" tabindex="-1" aria-hidden="true">
                                    <x-media::picture :media="$variant->image" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                                </a>
                                <div class="min-w-0 flex-1 space-y-1">
                                    @if ($variant->brandName)
                                        <p class="text-xs text-ink-500">{{ $variant->brandName }}</p>
                                    @endif
                                    <a href="{{ route('products.show', $variant->productSlug) }}" wire:navigate class="line-clamp-2 text-sm leading-6 text-ink-900 hover:text-primary-700 md:text-base">{{ $variant->productName }}</a>
                                    @if ($variant->hasOtherVariants)
                                        <p class="text-xs text-ink-500">{{ $variant->variantTitle }}</p>
                                    @endif
                                    @if ($line->isAvailable())
                                        <x-ui.price :price="$variant->price" :old="$variant->compareAtPrice" size="sm" />
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-col items-end justify-between gap-2">
                                    @if ($line->isAvailable())
                                        <x-cart::add-button :variant-id="$variant->variantId" :label="$variant->productName" />
                                        {{-- Removes the line whatever its quantity (owner, 2026-11-15). --}}
                                        <div class="-me-3 flex items-center gap-1">
                                            <p class="text-sm font-bold text-ink-900">@toman($line->lineTotal)</p>
                                            <button type="button" x-on:click="$store.cartRemoval.start({{ $variant->variantId }})" x-bind:class="$store.cartRemoval.isPending({{ $variant->variantId }}) && 'invisible'" class="grid size-11 place-items-center rounded-card text-ink-500 hover:bg-surface-50 hover:text-danger-600 focus-visible:outline-2 focus-visible:outline-primary-700" aria-label="حذف {{ $variant->productName }} از سبد">
                                                <x-ts-icon icon="trash" class="size-5" />
                                            </button>
                                        </div>
                                    @else
                                        <button type="button" x-show="! $store.cartRemoval.isPending({{ $variant->variantId }})" x-on:click="$store.cartRemoval.start({{ $variant->variantId }})" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                                            <x-ts-icon icon="trash" class="size-4" />
                                            حذف
                                        </button>
                                        <x-cart::removal-countdown :variant-id="$variant->variantId" :label="$variant->productName" class="h-11" />
                                    @endif
                                </div>
                            </div>

                            @if (! $line->isAvailable())
                                <p class="mt-3 flex items-center gap-2 rounded-md bg-surface-50 px-3 py-2 text-sm text-danger-600">
                                    <x-ts-icon icon="no-symbol" class="size-4 shrink-0" />
                                    {{ $variant->refusalMessage() }}
                                </p>
                            @elseif ($before !== null && $before !== $variant->price)
                                <p class="mt-3 flex items-center gap-2 rounded-md bg-surface-50 px-3 py-2 text-sm text-ink-900" role="status">
                                    <x-ts-icon icon="information-circle" @class(['size-4 shrink-0', 'text-success-600' => $variant->price < $before, 'text-danger-600' => $variant->price > $before]) />
                                    قیمت این کالا از {{ Toman::format($before) }} به {{ Toman::format($variant->price) }} {{ $variant->price < $before ? 'کاهش' : 'افزایش' }} یافت.
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <aside class="space-y-3 xl:sticky xl:top-[calc(var(--header-height,72px)+1rem)] xl:col-span-4" aria-label="خلاصه سبد">
                @if ($summary->freeShippingThreshold > 0)
                    {{-- While a quantity change is on its way the box softly blurs and fades; when this page renders the new
                         cart it clears and the bar slides to its new width (owner, 2026-11-15). 10 s at most. --}}
                    <div
                        x-data="{
                            updating: false,
                            timer: null,
                            forget: null,
                            init() {
                                this.forget = Livewire.hook('morphed', ({ component }) => component.id === $wire.$id && this.done())
                            },
                            destroy() {
                                this.forget?.()
                                clearTimeout(this.timer)
                            },
                            start() {
                                this.updating = true
                                clearTimeout(this.timer)
                                this.timer = setTimeout(() => this.done(), 10000)
                            },
                            done() {
                                this.updating = false
                                clearTimeout(this.timer)
                            },
                        }"
                        x-on:cart-add.window="start()"
                        x-on:cart-set.window="start()"
                        x-bind:class="updating && 'opacity-60 blur-[1.5px] saturate-50'"
                        x-bind:aria-busy="updating"
                        class="rounded-card border border-line-200 bg-card p-4 transition-[opacity,filter] duration-300 ease-out motion-reduce:transition-none"
                    >
                        <p class="text-sm text-ink-900">
                            @if ($summary->hasFreeShipping())
                                <x-ts-icon icon="truck" class="inline size-5 text-success-600" /> ارسال این سفارش رایگان است.
                            @else
                                {{ Toman::format($summary->remainingForFreeShipping()) }} تا ارسال رایگان
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-ink-500">ارسال رایگان برای خریدهای بالای {{ Toman::format($summary->freeShippingThreshold) }}</p>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-50" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $summary->freeShippingProgress() }}" aria-label="پیشرفت تا ارسال رایگان">
                            <div class="h-full rounded-full bg-primary-700 transition-[width] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]" style="width: {{ $summary->freeShippingProgress() }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="space-y-3 rounded-card border border-line-200 bg-card p-4">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-500">قیمت کالاها ({{ PersianDigits::toPersian($summary->itemCount) }})</dt><dd class="text-ink-900">@toman($summary->compareAtTotal)</dd></div>
                        @if ($summary->savings() > 0)
                            <div class="flex justify-between"><dt class="text-ink-500">سود شما از خرید</dt><dd class="text-success-600">@toman($summary->savings())</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-ink-500">هزینه ارسال</dt><dd class="text-ink-500">در مرحله بعد</dd></div>
                        <div class="flex justify-between border-t border-line-200 pt-2 text-base"><dt class="font-bold text-ink-900">جمع سبد</dt><dd class="font-bold text-ink-900">@toman($summary->subtotal)</dd></div>
                    </dl>
                    <div class="fixed inset-x-0 bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-30 border-t border-line-200 bg-card p-3 md:static md:border-0 md:p-0">
                        {{-- Phones: the total beside the button (owner, 2026-11-15); from tablets the card above shows it. --}}
                        <div class="flex items-center gap-4">
                            <p class="shrink-0 md:hidden">
                                <span class="block text-xs text-ink-500">جمع سبد</span>
                                <span class="block font-bold text-ink-900">@toman($summary->subtotal)</span>
                            </p>
                            <button
                                type="button"
                                wire:click="proceed"
                                @disabled($summary->hasUnavailable())
                                class="flex h-12 min-w-0 flex-1 items-center justify-center rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:bg-line-200 disabled:text-ink-500"
                            >
                                ادامه خرید
                            </button>
                        </div>
                        @if ($summary->hasUnavailable())
                            <p class="mt-2 text-center text-xs text-danger-600">ابتدا سبد را به‌روزرسانی کنید.</p>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
        {{-- Room for the phone's fixed «ادامه خرید» bar. --}}
        <div class="h-20 md:hidden" aria-hidden="true"></div>
    @endif
</div>
