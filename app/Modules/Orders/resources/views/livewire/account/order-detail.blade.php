{{-- One order (owner, 2026-11-14): see App\Modules\Orders\Livewire\Account\OrderDetail. Read only from the order's copies. --}}
@use('App\Modules\Identity\Support\AddressText')
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $status = $order->getStatus();
    $address = $order->getAddressSnapshot();
    $card = 'rounded-card border border-line-200 bg-card p-4 md:p-6';
@endphp

<x-identity::account-shell active="orders" title="جزئیات سفارش" :back="route('account.orders')" back-label="همه سفارش‌ها">

    <div class="{{ $card }}" x-data="{ copied: false }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1">
                <p class="text-lg font-bold text-ink-900 select-all" dir="ltr">{{ $order->getNumber() }}</p>
                <button type="button" x-on:click="$copy(@js($order->getNumber())).then((ok) => { copied = ok; setTimeout(() => (copied = false), 2000) })" class="grid size-11 place-items-center rounded-full text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700" aria-label="کپی شماره سفارش">
                    <x-ts-icon icon="clipboard-document" class="size-5" x-show="! copied" />
                    <x-ts-icon icon="check" class="size-5" x-show="copied" x-cloak />
                </button>
            </div>
            <x-orders::status-badge :status="$status" />
        </div>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
            <div><dt class="text-ink-500">{{ $order->getPaidAt() ? 'تاریخ پرداخت' : 'تاریخ ثبت' }}</dt><dd class="mt-0.5 text-ink-900">{{ JalaliDate::display($order->getOrderDate(), 'j F Y، H:i') }}</dd></div>
            <div><dt class="text-ink-500">مبلغ</dt><dd class="mt-0.5 font-bold text-ink-900">@toman($order->getTotal())</dd></div>
            <div><dt class="text-ink-500">پرداخت</dt><dd class="mt-0.5 text-ink-900">{{ $order->getPaymentStatus()->label() }}</dd></div>
        </dl>

        @if ($order->canBePaid() || $status !== OrderStatus::Pending)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line-200 pt-4">
                @if ($order->canBePaid())
                    <button type="button" wire:click="pay({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="credit-card" class="size-5" />
                        پرداخت
                    </button>
                    <span class="text-xs text-ink-500">مهلت پرداخت تا {{ JalaliDate::display($order->getExpiresAt(), 'H:i') }}</span>
                @else
                    {{-- «سفارش دوباره» is the page's main action, filled, at the top (owner, 2026-11-17). --}}
                    <button type="button" wire:click="reorder({{ $order->getId() }})" wire:loading.attr="disabled" wire:target="reorder" data-own-loading class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60">
                        <x-ts-icon icon="arrow-path" class="size-5" wire:loading.remove wire:target="reorder" />
                        <x-ui.spinner class="size-5" wire:loading wire:target="reorder" />
                        سفارش دوباره
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- The tracker (owner, 2026-11-16): an expired order never started, so it has none. --}}
    @if ($checkpoints !== [])
        <section aria-labelledby="order-progress" class="{{ $card }}">
            <h2 id="order-progress" class="font-bold text-ink-900">روند سفارش</h2>
            <x-orders::checkpoints :checkpoints="$checkpoints" class="mt-4" />

            {{-- The carrier's tracking code once the order is on its way (owner, 2026-11-16): the admin types it on «تحویل به حمل» until Tipax fills it (S27). --}}
            @if (in_array($status, [OrderStatus::OnTheWay, OrderStatus::Delivered], true))
                <div class="mt-5 rounded-card border border-primary-700/20 bg-primary-50 p-3 md:p-4" x-data="{ copiedCode: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-card text-primary-700">
                                <x-ts-icon icon="truck" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs text-primary-800">کد رهگیری مرسوله</p>
                                @if ($order->getTrackingCode())
                                    <p class="mt-0.5 text-lg font-bold tracking-wider text-ink-900 select-all" dir="ltr">{{ $order->getTrackingCode() }}</p>
                                @else
                                    <p class="mt-0.5 text-sm text-ink-500">کد رهگیری به‌زودی ثبت می‌شود.</p>
                                @endif
                            </div>
                        </div>
                        @if ($order->getTrackingCode())
                            <button type="button" x-on:click="$copy(@js($order->getTrackingCode())).then((ok) => { copiedCode = ok; setTimeout(() => (copiedCode = false), 2000) })" class="inline-flex min-h-11 items-center gap-1 rounded-card bg-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                                <x-ts-icon icon="clipboard-document" class="size-5" x-show="! copiedCode" />
                                <x-ts-icon icon="check" class="size-5" x-show="copiedCode" x-cloak />
                                <span x-text="copiedCode ? 'کپی شد' : 'کپی کد'">کپی کد</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endif

    <div class="grid gap-4 xl:grid-cols-3 xl:items-start">
        <div class="space-y-4 xl:col-span-2">
            <section aria-labelledby="order-items" class="{{ $card }}">
                <h2 id="order-items" class="font-bold text-ink-900">کالاها ({{ PersianDigits::toPersian($order->items->sum(fn ($item) => $item->getQuantity())) }})</h2>
                <ul class="mt-2 divide-y divide-line-200">
                    @foreach ($order->items as $item)
                        <li wire:key="order-item-{{ $item->getId() }}" class="flex gap-3 py-3">
                            <span class="size-16 shrink-0 overflow-hidden rounded-md border border-line-200 bg-media">
                                @if ($item->getImageUrl())
                                    <img src="{{ $item->getImageUrl() }}" alt="" width="64" height="64" class="size-full object-contain" loading="lazy">
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-ink-900">{{ $item->getProductName() }}</p>
                                <p class="mt-0.5 text-xs text-ink-500">{{ collect([$item->getVariantTitle(), $item->getBrandName()])->filter()->join(' · ') }}</p>
                                <p class="mt-1 text-xs text-ink-500">{{ PersianDigits::toPersian($item->getQuantity()) }} × @toman($item->getUnitPrice())</p>
                            </div>
                            <p class="shrink-0 text-sm font-bold text-ink-900">@toman($item->getPaidTotal())</p>
                        </li>
                    @endforeach
                </ul>
            </section>

        </div>

        <div class="space-y-4">
            <section aria-labelledby="order-address" class="{{ $card }}">
                <h2 id="order-address" class="font-bold text-ink-900">آدرس ارسال</h2>
                @if (filled($address['title'] ?? null))
                    <p class="mt-2 text-sm font-medium text-ink-900">{{ $address['title'] }}</p>
                @endif
                <p class="mt-1 text-sm leading-7 text-ink-900">{{ AddressText::from($address)->line() }}</p>
                <p class="mt-1 text-sm text-ink-500">
                    گیرنده: {{ $address['receiver_name'] ?? '' }}
                    @if (filled($address['receiver_mobile'] ?? null))
                        · <bdi dir="ltr">{{ PersianDigits::toPersian((string) $address['receiver_mobile']) }}</bdi>
                    @endif
                </p>
                @if (filled($address['postal_code'] ?? null))
                    <p class="text-sm text-ink-500">کد پستی <bdi dir="ltr">{{ PersianDigits::toPersian((string) $address['postal_code']) }}</bdi></p>
                @endif
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-line-200 pt-3 text-sm">
                    <span class="inline-flex items-center gap-1.5 text-ink-500">
                        <x-ts-icon icon="truck" class="size-5" />
                        هزینه ارسال
                    </span>
                    <span class="font-bold text-ink-900">@if ($order->getShippingTotal() === 0) رایگان @else @toman($order->getShippingTotal()) @endif</span>
                </div>
            </section>

            <section aria-labelledby="order-summary" class="{{ $card }}">
                <h2 id="order-summary" class="font-bold text-ink-900">خلاصه پرداخت</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">قیمت کالاها</dt><dd class="text-ink-900">@toman($order->getSubtotal())</dd></div>
                    @if ($order->getDiscountTotal() > 0)
                        <div class="flex justify-between gap-3"><dt class="text-ink-500">تخفیف{{ $order->getCouponCode() ? ' ('.$order->getCouponCode().')' : '' }}</dt><dd class="text-success-600">@toman($order->getDiscountTotal())</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">هزینه ارسال</dt><dd class="text-ink-900">@if ($order->getShippingTotal() === 0) رایگان @else @toman($order->getShippingTotal()) @endif</dd></div>
                    <div class="flex justify-between gap-3 border-t border-line-200 pt-2 text-base"><dt class="font-bold text-ink-900">مبلغ کل</dt><dd class="font-bold text-ink-900">@toman($order->getTotal())</dd></div>
                    @if ($reference)
                        <div class="flex justify-between gap-3"><dt class="text-ink-500">کد پیگیری پرداخت</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($reference) }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>

    {{-- «لغو سفارش» only here, at the bottom of the order page (owner, 2026-11-17), until the order is on its way. --}}
    @if ($status->isCancellableByCustomer())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-card border border-line-200 bg-card p-4">
            <p class="text-sm text-ink-500">تا پیش از ارسال می‌توانید سفارش را لغو کنید؛ مبلغ پرداختی بازگردانده می‌شود.</p>
            <button type="button" x-data="{ working: false }" x-bind:disabled="working" x-bind:aria-busy="working" x-on:click="$store.confirm.ask({ title: 'لغو سفارش', message: @js('سفارش '.$order->getNumber().' لغو شود؟ مبلغ پرداختی بازگردانده می‌شود.'), confirm: 'لغو سفارش', cancel: 'منصرف شدم', icon: 'cancel' }).then((ok) => { if (! ok) return; working = true; $wire.cancel({{ $order->getId() }}).finally(() => (working = false)); })" class="inline-flex min-h-11 items-center gap-1 rounded-card border border-danger-600 px-4 text-sm font-medium text-danger-600 hover:bg-surface-50 disabled:cursor-wait disabled:opacity-60">
                <x-ts-icon icon="x-circle" class="size-5" x-show="! working" />
                <x-ui.spinner class="size-5" x-show="working" x-cloak />
                لغو سفارش
            </button>
        </div>
    @endif
</x-identity::account-shell>
