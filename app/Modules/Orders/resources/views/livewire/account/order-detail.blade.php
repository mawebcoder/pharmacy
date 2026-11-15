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

<x-identity::account-shell active="orders" title="جزئیات سفارش">
    <a href="{{ route('account.orders') }}" wire:navigate class="hidden min-h-11 items-center gap-1 text-sm text-primary-700 hover:underline xl:inline-flex">
        <x-ts-icon icon="chevron-right" class="size-4" />
        همه سفارش‌ها
    </a>

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
            <div><dt class="text-ink-500">تاریخ ثبت</dt><dd class="mt-0.5 text-ink-900">{{ JalaliDate::display($order->getPlacedAt(), 'j F Y، H:i') }}</dd></div>
            <div><dt class="text-ink-500">مبلغ</dt><dd class="mt-0.5 font-bold text-ink-900">@toman($order->getTotal())</dd></div>
            <div><dt class="text-ink-500">پرداخت</dt><dd class="mt-0.5 text-ink-900">{{ $order->getPaymentStatus()->label() }}</dd></div>
        </dl>

        @if ($order->canBePaid() || $status === OrderStatus::Paid || $status !== OrderStatus::Pending)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line-200 pt-4">
                @if ($order->canBePaid())
                    <button type="button" wire:click="pay({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="credit-card" class="size-5" />
                        پرداخت
                    </button>
                    <span class="text-xs text-ink-500">مهلت پرداخت تا {{ JalaliDate::display($order->getExpiresAt(), 'H:i') }}</span>
                @else
                    @if ($status === OrderStatus::Paid)
                        <button type="button" x-data="{ working: false }" x-bind:disabled="working" x-bind:aria-busy="working" x-on:click="$store.confirm.ask({ title: 'لغو سفارش', message: @js('سفارش '.$order->getNumber().' لغو شود؟ مبلغ پرداختی بازگردانده می‌شود.'), confirm: 'لغو سفارش', cancel: 'منصرف شدم', icon: 'cancel' }).then((ok) => { if (! ok) return; working = true; $wire.cancel({{ $order->getId() }}).finally(() => (working = false)); })" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-danger-600 hover:bg-surface-50 disabled:cursor-wait disabled:opacity-60">
                            <x-ts-icon icon="x-circle" class="size-5" x-show="! working" />
                            <x-ui.spinner class="size-5" x-show="working" x-cloak />
                            لغو سفارش
                        </button>
                    @endif
                    @if ($status !== OrderStatus::Pending)
                        <button type="button" wire:click="reorder({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-primary-700 hover:bg-primary-50">
                            <x-ts-icon icon="arrow-path" class="size-5" />
                            سفارش دوباره
                        </button>
                    @endif
                @endif
            </div>
        @endif
    </div>

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
                            <p class="shrink-0 text-sm font-bold text-ink-900">@toman($item->getTotal())</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="order-history" class="{{ $card }}">
                <h2 id="order-history" class="font-bold text-ink-900">روند سفارش</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($order->statusLogs as $log)
                        <li class="flex gap-3">
                            <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-primary-700' => $loop->last, 'bg-line-300' => ! $loop->last])></span>
                            <div>
                                <p @class(['text-sm', 'font-bold text-ink-900' => $loop->last, 'text-ink-900' => ! $loop->last])>{{ $log->getToStatus()->label() }}</p>
                                @if ($log->getCreatedAt())
                                    <p class="text-xs text-ink-500">{{ JalaliDate::display($log->getCreatedAt(), 'j F Y، H:i') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
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
</x-identity::account-shell>
