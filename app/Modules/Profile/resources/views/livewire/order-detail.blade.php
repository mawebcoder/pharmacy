{{-- One order (owner, 2026-11-14): see App\Modules\Profile\Livewire\OrderDetail. Read only from the order's copies. --}}
@use('App\Modules\Identity\Support\AddressText')
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Orders\Support\PrintFrame')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $status = $order->getStatus();
    $address = $order->getAddressSnapshot();
    $card = 'rounded-card border border-line-200 bg-card p-4 md:p-6';
@endphp

<x-profile::account-shell active="orders" title="جزئیات سفارش" :back="route('account.orders')" back-label="همه سفارش‌ها">

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

        <div class="mt-4">
            <x-orders::payment-countdown :order="$order" />
        </div>

        {{-- «ویرایش سفارش» while unpaid (owner, 2026-11-19): quantities (0 removes), another saved address; priced again. --}}
        @if ($order->canBePaid())
            @if (! $editing)
                <button type="button" wire:click="startEdit" class="mt-3 inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="pencil-square" class="size-5" />
                    ویرایش سفارش
                </button>
            @else
                @php($fieldClass = 'h-11 w-full rounded-card border border-line-300 bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700')
                <form wire:submit="saveEdit" class="mt-4 space-y-3 rounded-card border border-line-200 p-4" data-test="order-edit">
                    <p class="text-sm text-ink-500">تعداد هر کالا را تغییر دهید (صفر یعنی حذف) یا آدرس دیگری انتخاب کنید. قیمت‌ها و هزینه ارسال دوباره حساب می‌شوند و مهلت پرداخت تغییر نمی‌کند. برای افزودن کالای تازه، سفارش جدیدی ثبت کنید.</p>
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3" wire:key="edit-{{ $item->getId() }}">
                            <label for="edit-qty-{{ $item->getVariantId() }}" class="text-sm text-ink-900">{{ trim($item->getProductName().' '.($item->getVariantTitle() ?? '')) }}</label>
                            <select id="edit-qty-{{ $item->getVariantId() }}" wire:model="editQuantities.{{ $item->getVariantId() }}" class="h-11 w-24 rounded-card border border-line-300 bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700">
                                @foreach (range(0, max(10, $item->getQuantity())) as $count)
                                    <option value="{{ $count }}">{{ $count === 0 ? 'حذف' : PersianDigits::toPersian((string) $count) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="space-y-1.5">
                        <label for="edit-address" class="block text-sm font-medium text-ink-900">آدرس ارسال</label>
                        <select id="edit-address" wire:model="editAddressId" class="{{ $fieldClass }}">
                            @foreach ($addressOptions as $addressId => $label)
                                <option value="{{ $addressId }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveEdit" class="inline-flex min-h-11 items-center gap-1 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60">ذخیره تغییرات</button>
                        <button type="button" wire:click="cancelEdit" class="inline-flex min-h-11 items-center rounded-card px-3 text-sm font-medium text-ink-500 hover:bg-surface-50">انصراف</button>
                    </div>
                </form>
            @endif
        @endif

        @if ($order->canBePaid() || $status !== OrderStatus::Pending)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line-200 pt-4">
                @if ($order->canBePaid() || $order->canPayDifference())
                    <button type="button" wire:click="pay({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="credit-card" class="size-5" />
                        {{ $order->canPayDifference() ? 'پرداخت تفاوت مبلغ' : 'پرداخت' }}
                    </button>
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

            {{-- The carrier's tracking code once the order is on its way (owner, 2026-11-16): the admin types it on «تحویل به حمل» until Tipax fills it (S35). --}}
            @if (in_array($status, [OrderStatus::OnTheWay, OrderStatus::Delivered], true))
                <div class="mt-5 rounded-card border border-primary-700/20 bg-primary-50 p-3 md:p-4">
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
                    </div>

                    {{-- The parcel's progress from the carrier while on its way (S37, owner 2026-11-19): shown from the last answer;
                         «استعلام از سرویس ارسال» asks again, an hour after the last successful answer at the soonest. --}}
                    @if ($status === OrderStatus::OnTheWay && $order->getTrackingCode())
                        <div class="mt-3 border-t border-primary-700/15 pt-3 text-sm" data-test="parcel-status">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-primary-800">وضعیت مرسوله: <span class="font-bold text-ink-900">{{ $parcel?->status ?? 'هنوز استعلام نشده' }}</span></p>
                                <button type="button" wire:click="inquireParcel" wire:loading.attr="disabled" wire:target="inquireParcel" class="inline-flex min-h-11 items-center gap-1 rounded-card bg-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:opacity-60">
                                    <x-ts-icon icon="arrow-path" class="size-5" wire:loading.class="animate-spin" wire:target="inquireParcel" />
                                    استعلام از سرویس ارسال
                                </button>
                            </div>
                            @if ($parcelNotice)
                                <p class="mt-2 text-xs text-ink-500" role="status">{{ $parcelNotice }}</p>
                            @endif
                            @if ($parcel)
                                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @if ($parcel->destinationCity)
                                        <div class="flex justify-between gap-3"><dt class="text-ink-500">مقصد</dt><dd class="text-ink-900">{{ $parcel->destinationCity }}</dd></div>
                                    @endif
                                    @if ($parcel->branch)
                                        <div class="flex justify-between gap-3"><dt class="text-ink-500">نمایندگی</dt><dd class="text-ink-900">{{ $parcel->branch }}</dd></div>
                                    @endif
                                    @if ($parcel->deliveredAt)
                                        <div class="flex justify-between gap-3"><dt class="text-ink-500">زمان تحویل</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($parcel->deliveredAt) }}</dd></div>
                                    @endif
                                    @if ($parcel->syncedAt)
                                        <p class="text-xs text-ink-500 sm:col-span-2">آخرین به‌روزرسانی: {{ JalaliDate::display($parcel->syncedAt, 'j F Y، H:i') }}</p>
                                    @endif
                                </dl>
                            @endif
                        </div>
                    @endif
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
                                    <picture>@if ($item->getImageAvifUrl())<source type="image/avif" srcset="{{ $item->getImageAvifUrl() }}">@endif<img src="{{ $item->getImageUrl() }}" alt="" width="64" height="64" class="size-full object-contain" loading="lazy" decoding="async"></picture>
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
                {{-- «فاکتور فروش» of a paid order (owner, 2026-11-19): opens the print dialog on this page, no new tab. --}}
                @if ($order->getPaidAt())
                    <button type="button" x-data x-on:click="{{ PrintFrame::script(route('account.orders.invoice', $order->getId())) }}" class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-1 rounded-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="printer" class="size-5" />
                        چاپ فاکتور
                    </button>
                @endif
            </section>
        </div>
    </div>

    {{-- What the customer can still do (owner, 2026-11-19): cancel only before it is on its way; no edits once it is with
         the delivery partner; a return only after delivery, within 24 hours. --}}
    @if ($status === OrderStatus::OnTheWay)
        <p class="mt-4 rounded-card border border-line-200 bg-surface-50 p-4 text-sm leading-7 text-ink-900" role="note" data-test="order-on-the-way-note">
            سفارش به سرویس ارسال تحویل داده شده است و دیگر قابل ویرایش یا لغو نیست. پس از دریافت کالاها، وقتی وضعیت سفارش در همین صفحه «تحویل شد» شد، تا ۲۴ ساعت می‌توانید برای کالاها درخواست مرجوعی ثبت کنید.
        </p>
    @endif

    {{-- Returns (S27, owner 2026-11-19): the order's returns, and «درخواست مرجوعی» within 24 hours of delivery. --}}
    @if ($returns !== [] || $mayRequestReturn)
        @php($fieldClass = 'h-11 w-full rounded-card border border-line-300 bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700')
        <section aria-labelledby="order-returns" class="mt-4 {{ $card }}" data-test="order-returns">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="order-returns" class="font-bold text-ink-900">مرجوعی</h2>
                @if ($mayRequestReturn && $returnRows === [])
                    <button type="button" wire:click="startReturn" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="arrow-uturn-left" class="size-5" />
                        درخواست مرجوعی
                    </button>
                @endif
            </div>
            @if ($mayRequestReturn && $returnUntil)
                <p class="mt-1 text-sm text-ink-500">تا {{ JalaliDate::display($returnUntil, 'j F، ساعت H:i') }} می‌توانید برای کالاهای این سفارش درخواست مرجوعی ثبت کنید.</p>
            @endif

            @if ($returnRows !== [])
                <form wire:submit="requestReturn" class="mt-4 space-y-3" novalidate>
                    @foreach ($returnRows as $index => $row)
                        <fieldset wire:key="return-row-{{ $index }}" class="grid gap-3 rounded-card border border-line-200 p-3 md:grid-cols-6">
                            <legend class="sr-only">ردیف {{ PersianDigits::toPersian((string) ($index + 1)) }}</legend>
                            <div class="space-y-1.5 md:col-span-2">
                                <label for="return-item-{{ $index }}" class="block text-sm font-medium text-ink-900">کالا</label>
                                <select id="return-item-{{ $index }}" wire:model.live="returnRows.{{ $index }}.order_item_id" class="{{ $fieldClass }}">
                                    <option value="">انتخاب کالا</option>
                                    @foreach ($returnItems as $itemId => $item)
                                        <option value="{{ $itemId }}">{{ $item['title'] }}</option>
                                    @endforeach
                                </select>
                                @error('returnRows.'.$index.'.order_item_id') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="return-quantity-{{ $index }}" class="block text-sm font-medium text-ink-900">تعداد</label>
                                <select id="return-quantity-{{ $index }}" wire:model="returnRows.{{ $index }}.quantity" class="{{ $fieldClass }}">
                                    @foreach (range(1, max(1, $returnItems[(int) ($row['order_item_id'] ?? 0)]['left'] ?? 1)) as $count)
                                        <option value="{{ $count }}">{{ PersianDigits::toPersian((string) $count) }}</option>
                                    @endforeach
                                </select>
                                @error('returnRows.'.$index.'.quantity') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="return-reason-{{ $index }}" class="block text-sm font-medium text-ink-900">دلیل</label>
                                <select id="return-reason-{{ $index }}" wire:model="returnRows.{{ $index }}.reason" class="{{ $fieldClass }}">
                                    @foreach ($returnReasons as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('returnRows.'.$index.'.reason') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-end gap-2 md:col-span-2">
                                <div class="flex-1 space-y-1.5">
                                    <label for="return-note-{{ $index }}" class="block text-sm font-medium text-ink-900">توضیح (اختیاری)</label>
                                    <input id="return-note-{{ $index }}" type="text" wire:model="returnRows.{{ $index }}.note" maxlength="500" class="{{ $fieldClass }}">
                                </div>
                                @if (count($returnRows) > 1)
                                    <button type="button" wire:click="removeReturnRow({{ $index }})" class="grid size-11 shrink-0 place-items-center rounded-card text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700" aria-label="حذف ردیف">
                                        <x-ts-icon icon="trash" class="size-5" />
                                    </button>
                                @endif
                            </div>
                        </fieldset>
                    @endforeach
                    <p class="text-xs text-ink-500">یک کالا را می‌توانید با دلیل‌های مختلف در چند ردیف بیاورید؛ جمع آن‌ها از تعداد خریداری‌شده بیشتر نمی‌شود.</p>
                    <div>
                        <label for="return-note" class="block text-sm font-medium text-ink-900">توضیح کلی (اختیاری)</label>
                        <textarea id="return-note" wire:model="returnNote" rows="2" maxlength="2000" class="mt-1.5 w-full rounded-card border border-line-300 bg-card p-3 text-sm leading-7 text-ink-900 focus:outline-2 focus:outline-primary-700"></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="addReturnRow" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm font-medium text-primary-700 ring-1 ring-primary-700/20 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="plus" class="size-5" />
                            افزودن ردیف
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="requestReturn" class="inline-flex min-h-11 items-center gap-1 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60">
                            ثبت درخواست مرجوعی
                        </button>
                    </div>
                </form>
            @endif

            @foreach ($returns as $return)
                <div class="mt-4 rounded-card border border-line-200 p-3 text-sm" wire:key="return-{{ $return['number'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-bold text-ink-900" dir="ltr">{{ PersianDigits::toPersian($return['number']) }}</span>
                        <span class="rounded-full bg-surface-50 px-2.5 py-1 text-xs font-medium text-primary-800">{{ $return['status'] }}</span>
                    </div>
                    <ul class="mt-2 space-y-1 text-ink-900">
                        @foreach ($return['rows'] as $row)
                            <li>{{ $row['title'] }} · {{ PersianDigits::toPersian((string) $row['quantity']) }} عدد · {{ $row['reason'] }}</li>
                        @endforeach
                    </ul>
                    @if ($return['tracking_code'])
                        <p class="mt-2 text-ink-500">کد رهگیری جمع‌آوری: <bdi dir="ltr">{{ PersianDigits::toPersian($return['tracking_code']) }}</bdi></p>
                    @endif
                    @if ($return['refund'] !== null)
                        <p class="mt-1 font-medium text-success-600">مبلغ بازگردانده‌شده: @toman($return['refund'])</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    {{-- «لغو سفارش» only here, at the bottom of the order page (owner, 2026-11-17), until the order is on its way. --}}
    @if ($status->isCancellableByCustomer())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-card border border-line-200 bg-card p-4">
            <p class="text-sm text-ink-500">فقط تا پیش از «در راه» (تحویل به سرویس ارسال) می‌توانید سفارش را لغو کنید؛ مبلغ پرداختی بازگردانده می‌شود. پس از آن، مرجوعی فقط پس از دریافت کالاها ممکن است.</p>
            <button type="button" x-data="{ working: false }" x-bind:disabled="working" x-bind:aria-busy="working" x-on:click="$store.confirm.ask({ title: 'لغو سفارش', message: @js('سفارش '.$order->getNumber().' لغو شود؟ مبلغ پرداختی بازگردانده می‌شود.'), confirm: 'لغو سفارش', cancel: 'منصرف شدم', icon: 'cancel' }).then((ok) => { if (! ok) return; working = true; $wire.cancel({{ $order->getId() }}).finally(() => (working = false)); })" class="inline-flex min-h-11 items-center gap-1 rounded-card border border-danger-600 px-4 text-sm font-medium text-danger-600 hover:bg-surface-50 disabled:cursor-wait disabled:opacity-60">
                <x-ts-icon icon="x-circle" class="size-5" x-show="! working" />
                <x-ui.spinner class="size-5" x-show="working" x-cloak />
                لغو سفارش
            </button>
        </div>
    @endif
</x-profile::account-shell>
