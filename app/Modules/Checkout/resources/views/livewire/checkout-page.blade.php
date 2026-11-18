{{-- Checkout step 2 «بازبینی و پرداخت» (roadmap B9.7): see App\Modules\Checkout\Livewire\CheckoutPage. --}}
@use('App\Modules\Shared\Support\PersianDigits')
@use('App\Modules\Shared\Support\Toman')

<div class="space-y-4">
    <x-checkout::stepper current="review" />
    <h1 class="text-xl font-bold text-ink-900 xl:text-2xl">بازبینی و پرداخت</h1>

    @if ($notice)
        <div role="alert" class="flex items-start gap-2 rounded-card border border-warning-500 bg-card p-4 text-sm leading-7 text-ink-900">
            <x-ts-icon icon="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-warning-500" />
            {{ $notice }}
        </div>
    @endif

    @if (! $quote || $quote->cart->isEmpty())
        <x-identity::empty-state icon="shopping-bag" message="سبد خرید شما خالی است." :action-href="route('home')" />
    @else
        <div class="grid gap-4 xl:grid-cols-12 xl:items-start">
            <div class="space-y-4 xl:col-span-8">
                {{-- Address: the picked one, else the default. --}}
                <section class="rounded-card border border-line-200 bg-card p-4" aria-labelledby="checkout-address">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="checkout-address" class="flex items-center gap-2 font-bold text-ink-900">
                            <x-ts-icon icon="map-pin" class="size-5 text-primary-700" />
                            آدرس ارسال
                        </h2>
                        <a href="{{ route('checkout.address') }}" wire:navigate class="min-h-11 content-center text-sm text-primary-700 hover:underline">تغییر آدرس</a>
                    </div>
                    @if ($address = $quote->address)
                        <p class="mt-2 text-sm font-medium text-ink-900">{{ $address->title }}</p>
                        <p class="mt-1 text-sm leading-7 text-ink-900">{{ $address->text()->line() }}</p>
                        <p class="mt-1 text-sm text-ink-500">
                            گیرنده: {{ $address->receiverName }} · <span dir="ltr">{{ PersianDigits::toPersian($address->receiverMobile) }}</span> · کد پستی <span dir="ltr">{{ PersianDigits::toPersian($address->postalCode) }}</span>
                        </p>
                        @unless ($address->isDeliveryEnabled)
                            <p class="mt-2 text-sm text-danger-600">ارسال به این شهر فعلاً ممکن نیست؛ آدرس دیگری انتخاب کنید.</p>
                        @endunless
                    @endif
                </section>

                {{-- Shipping (S34, owner 2026-11-18): one price, the cheapest Tipax service for the packed cart. --}}
                <section class="rounded-card border border-line-200 bg-card p-4" aria-labelledby="checkout-shipping">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="checkout-shipping" class="flex items-center gap-2 font-bold text-ink-900">
                            <x-ts-icon icon="truck" class="size-5 text-primary-700" />
                            هزینه ارسال
                        </h2>
                        @if ($quote->shippingPrice === 0)
                            <span class="font-medium text-success-600">رایگان</span>
                        @else
                            <span class="text-ink-900">@toman($quote->shippingPrice)</span>
                        @endif
                    </div>
                    {{-- Where to follow the parcel (owner, 2026-11-15); plain text, nothing on checkout leads away from paying. --}}
                    <p class="mt-2 flex items-start gap-1.5 text-xs leading-6 text-ink-500">
                        <x-ts-icon icon="information-circle" class="mt-0.5 size-4 shrink-0" />
                        <span>پس از ارسال، کد رهگیری مرسوله در «حساب کاربری › سفارش‌ها»، در جزئیات همین سفارش نمایش داده می‌شود.</span>
                    </p>
                </section>

                {{-- Items (collapsed on phones). --}}
                <section class="rounded-card border border-line-200 bg-card" x-data="{ open: window.matchMedia('(min-width: 768px)').matches }" aria-labelledby="checkout-items">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="checkout-items-list" class="flex min-h-12 w-full items-center justify-between gap-2 px-4 text-start">
                        <h2 id="checkout-items" class="font-bold text-ink-900">کالاها ({{ PersianDigits::toPersian($quote->cart->itemCount) }})</h2>
                        <x-ts-icon icon="chevron-down" class="size-4 text-ink-500 transition" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <ul id="checkout-items-list" x-show="open" x-cloak class="divide-y divide-line-200 border-t border-line-200">
                        @foreach ($quote->cart->lines as $line)
                            @php($variant = $line->variant)
                            <li wire:key="checkout-line-{{ $variant->variantId }}" @class(['flex items-center gap-3 p-3', 'opacity-60' => ! $line->isAvailable()])>
                                <span class="size-12 shrink-0 overflow-hidden rounded-md border border-line-200 bg-media">
                                    <x-media::picture :media="$variant->image" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-1 text-sm text-ink-900">{{ $variant->productName }}</p>
                                    <p class="text-xs text-ink-500">
                                        {{ PersianDigits::toPersian($line->quantity) }} عدد
                                        @if ($variant->hasOtherVariants) · {{ $variant->variantTitle }} @endif
                                        @unless ($line->isAvailable()) · <span class="text-danger-600">{{ $variant->refusalMessage() }}</span> @endunless
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm text-ink-900">@toman($line->lineTotal)</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($quote->unavailableNames() !== [])
                    <div role="alert" class="rounded-card border border-danger-600 bg-card p-4 text-sm leading-7 text-danger-600">
                        <p class="font-bold">این کالاها دیگر قابل خرید نیستند:</p>
                        <ul class="list-disc ps-5">
                            @foreach ($quote->unavailableNames() as $name)
                                <li>{{ $name }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-ink-900">برای ادامه، آن‌ها را از <a href="{{ route('cart') }}" wire:navigate class="font-bold text-primary-700 underline">سبد خرید</a> حذف کنید.</p>
                    </div>
                @endif

                <section class="rounded-card border border-line-200 bg-card p-4">
                    <label for="checkout-note" class="block text-sm font-medium text-ink-900">یادداشت برای سفارش (اختیاری)</label>
                    <textarea id="checkout-note" wire:model="note" rows="2" maxlength="500" class="mt-1.5 w-full rounded-card border border-line-300 bg-card p-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700"></textarea>
                </section>
            </div>

            <aside class="space-y-3 xl:sticky xl:top-[calc(var(--header-height,72px)+1rem)] xl:col-span-4" aria-label="خلاصه سفارش">
                {{-- Coupon: worked out on the fly; applying only logs it. --}}
                <div class="rounded-card border border-line-200 bg-card" x-data="{ open: @js($errors->has('couponInput')) }">
                    @if ($quote->coupon)
                        <div class="flex items-start gap-3 p-4">
                            <x-ts-icon icon="check-circle" class="mt-0.5 size-5 shrink-0 text-success-600" />
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-bold text-ink-900">کد <span dir="ltr">{{ $quote->coupon->code }}</span></p>
                                <p class="mt-0.5 text-success-600">{{ $quote->coupon->freeShipping ? 'ارسال رایگان' : Toman::format($quote->coupon->discount).' تخفیف' }}</p>
                            </div>
                            <button type="button" wire:click="removeCoupon" class="min-h-11 shrink-0 px-2 text-sm text-danger-600 hover:underline">حذف</button>
                        </div>
                    @else
                        @if ($quote->couponProblem)
                            <p role="status" class="flex items-start gap-2 p-4 pb-0 text-sm text-ink-900">
                                <x-ts-icon icon="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-warning-500" />
                                {{ $quote->couponProblem }}
                            </p>
                        @endif
                        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="checkout-coupon" class="flex min-h-12 w-full items-center justify-between gap-2 px-4 text-sm text-ink-900 hover:bg-surface-50">
                            <span class="flex items-center gap-2"><x-ts-icon icon="ticket" class="size-5 text-primary-700" /> کد تخفیف دارید؟</span>
                            <x-ts-icon icon="chevron-down" class="size-4 text-ink-500 transition" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <form id="checkout-coupon" x-show="open" x-cloak wire:submit="applyCoupon" class="border-t border-line-200 p-4" novalidate>
                            <label for="checkout-coupon-code" class="sr-only">کد تخفیف</label>
                            <div class="flex gap-2">
                                <input id="checkout-coupon-code" type="text" wire:model="couponInput" dir="ltr" autocomplete="off" maxlength="32" placeholder="کد تخفیف"
                                    @error('couponInput') aria-invalid="true" aria-describedby="checkout-coupon-error" @enderror
                                    @class(['h-11 min-w-0 flex-1 rounded-card border bg-card px-3 text-center uppercase tracking-wider text-ink-900 placeholder:normal-case placeholder:tracking-normal focus:outline-2 focus:outline-primary-700', 'border-line-300' => ! $errors->has('couponInput'), 'border-danger-600' => $errors->has('couponInput')])>
                                <button type="submit" wire:loading.attr="disabled" wire:target="applyCoupon" class="h-11 shrink-0 rounded-card border border-primary-700 px-4 text-sm font-medium text-primary-700 hover:bg-primary-50 disabled:opacity-60">اعمال</button>
                            </div>
                            @error('couponInput')
                                <p id="checkout-coupon-error" class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif
                </div>

                <div class="space-y-3 rounded-card border border-line-200 bg-card p-4">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-500">قیمت کالاها</dt><dd class="text-ink-900">@toman($quote->cart->compareAtTotal)</dd></div>
                        @if ($quote->cart->savings() > 0)
                            <div class="flex justify-between"><dt class="text-ink-500">سود شما از خرید</dt><dd class="text-success-600">@toman($quote->cart->savings())</dd></div>
                        @endif
                        @if ($quote->discount() > 0)
                            <div class="flex justify-between"><dt class="text-ink-500">تخفیف کد <span dir="ltr">{{ $quote->coupon?->code }}</span></dt><dd class="text-success-600">@toman($quote->discount())</dd></div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-ink-500">هزینه ارسال</dt>
                            @if ($quote->shippingPrice === 0)
                                <dd class="font-medium text-success-600">رایگان</dd>
                            @else
                                <dd class="text-ink-900">@toman($quote->shippingPrice)</dd>
                            @endif
                        </div>
                        <div class="flex justify-between border-t border-line-200 pt-2 text-base"><dt class="font-bold text-ink-900">مبلغ قابل پرداخت</dt><dd class="font-bold text-ink-900">@toman($quote->total())</dd></div>
                    </dl>


                    <div class="fixed inset-x-0 bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-30 border-t border-line-200 bg-card p-3 md:static md:border-0 md:p-0">
                        <button
                            type="button"
                            wire:click="pay" data-own-loading
                            wire:loading.attr="disabled"
                            wire:target="pay"
                            @disabled($quote->unavailableNames() !== [] || ! $quote->address?->isDeliveryEnabled)
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:bg-line-200 disabled:text-ink-500"
                        >
                            <x-ts-icon icon="arrow-path" class="size-5 animate-spin" wire:loading wire:target="pay" />
                            @if ($quote->unavailableNames() !== [])
                                ابتدا سبد خرید را اصلاح کنید
                            @else
                                پرداخت @toman($quote->total())
                            @endif
                        </button>
                    </div>
                </div>
            </aside>
        </div>
        {{-- Room for the phone's fixed «پرداخت» bar. --}}
        <div class="h-20 md:hidden" aria-hidden="true"></div>
    @endif
</div>
