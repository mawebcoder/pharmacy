{{-- «سفارش‌ها» (roadmap B9.9): see App\Modules\Profile\Livewire\OrderList. --}}
@use('App\Modules\Orders\Enums\CustomerOrderTab')
@use('App\Modules\Orders\Enums\OrderReturnStatus')
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

<x-profile::account-shell active="orders" title="سفارش‌ها">
    {{-- A tab per status, with its count, and the orders with returns (owner, 2026-11-19); swiped sideways on phones. --}}
    <nav aria-label="فیلتر سفارش‌ها" class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:px-0">
        <ul class="flex w-max gap-2 border-b border-line-200 md:w-full">
            @foreach ($tabs as $tab)
                <li>
                    <button type="button" wire:click="showTab('{{ $tab->value }}')" @if ($tab === $activeTab) aria-current="page" @endif @class([
                        '-mb-px inline-flex min-h-11 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm focus-visible:outline-2 focus-visible:outline-primary-700',
                        'border-primary-700 font-bold text-primary-700' => $tab === $activeTab,
                        'border-transparent text-ink-500 hover:text-ink-900' => $tab !== $activeTab,
                    ])>
                        {{ $tab->label() }}
                        <span class="rounded-full bg-surface-50 px-1.5 text-xs text-ink-500">{{ PersianDigits::toPersian($tabCounts[$tab->value] ?? 0) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($orders->isEmpty())
        <x-identity::empty-state icon="shopping-bag" :message="$activeTab === CustomerOrderTab::All ? 'هنوز سفارشی ثبت نکرده‌اید.' : 'سفارشی در «'.$activeTab->label().'» ندارید.'" hint="سفارش‌های شما و وضعیت ارسالشان اینجا نمایش داده می‌شود." />
    @else
        {{-- Unpaid orders first get a warning with their payment deadline (owner, 2026-11-19). --}}
        @php($unpaid = $orders->filter(fn ($order) => $order->canBePaid() || $order->canPayDifference())->count())
        @if ($unpaid > 0)
            <p class="mb-3 flex items-center gap-2 rounded-card border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-ink-900" role="alert">
                <x-ts-icon icon="exclamation-triangle" class="size-5 shrink-0" />
                {{ PersianDigits::toPersian($unpaid) }} سفارش در انتظار پرداخت دارید؛ پیش از پایان مهلت پرداخت کنید، وگرنه لغو می‌شوند.
            </p>
        @endif
        <ul class="space-y-3">
            @foreach ($orders as $order)
                @php($status = $order->getStatus())
                <li wire:key="order-{{ $order->getId() }}" class="relative rounded-card border border-line-200 bg-card p-4 transition-colors hover:border-primary-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        {{-- The whole card opens the order; the action buttons sit above the link. --}}
                        <a href="{{ route('account.orders.show', $order->getId()) }}" wire:navigate class="font-bold text-ink-900 after:absolute after:inset-0 after:rounded-card focus-visible:outline-none after:focus-visible:outline-2 after:focus-visible:outline-primary-700" dir="ltr">{{ $order->getNumber() }}<span class="sr-only"> — جزئیات سفارش</span></a>
                        <x-orders::status-badge :status="$status" />
                    </div>
                    <p class="mt-1 text-sm text-ink-500">{{ JalaliDate::display($order->getOrderDate(), 'j F Y، H:i') }}</p>
                    <div class="relative z-10 mt-2">
                        <x-orders::payment-countdown :order="$order" compact />
                    </div>

                    {{-- The returns tab shows each return, full or partial, with its items (owner, 2026-11-19). --}}
                    @if ($activeTab === CustomerOrderTab::Returned)
                        @foreach ($order->returns->filter(fn ($return) => $return->getStatus() !== OrderReturnStatus::Cancelled) as $return)
                            <div class="relative z-10 mt-3 rounded-card border border-line-200 p-3 text-sm" wire:key="list-return-{{ $return->getId() }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-ink-900" dir="ltr">{{ PersianDigits::toPersian($return->getNumber()) }}</span>
                                    <span class="rounded-full bg-surface-50 px-2 py-0.5 text-xs font-medium text-primary-800">{{ $return->getScope()->label() }}</span>
                                    <span class="text-xs text-ink-500">{{ $return->getStatus()->customerLabel() }}</span>
                                </div>
                                <ul class="mt-2 space-y-1 text-ink-900">
                                    @foreach ($return->items as $row)
                                        @php($item = $order->items->firstWhere('id', $row->getOrderItemId()))
                                        <li>{{ trim(($item?->getProductName() ?? '').' '.($item?->getVariantTitle() ?? '')) }} · {{ PersianDigits::toPersian((string) $row->getQuantity()) }} عدد · {{ $row->getReason()->label() }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    @endif

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="flex -space-x-2" aria-label="{{ PersianDigits::toPersian($order->items->count()) }} کالا">
                            @foreach ($order->items->take(4) as $item)
                                <span class="size-11 overflow-hidden rounded-full border-2 border-card bg-media ring-1 ring-line-200">
                                    @if ($item->getImageUrl())
                                        <picture>@if ($item->getImageAvifUrl())<source type="image/avif" srcset="{{ $item->getImageAvifUrl() }}">@endif<img src="{{ $item->getImageUrl() }}" alt="" width="44" height="44" class="size-full object-contain" loading="lazy" decoding="async"></picture>
                                    @endif
                                </span>
                            @endforeach
                            @if ($order->items->count() > 4)
                                <span class="grid size-11 place-items-center rounded-full border-2 border-white bg-surface-50 text-xs text-ink-500 ring-1 ring-line-200">+{{ PersianDigits::toPersian($order->items->count() - 4) }}</span>
                            @endif
                        </div>
                        <p class="text-base font-bold text-ink-900">@toman($order->getTotal())</p>
                    </div>

                    <div class="relative z-10 mt-3 flex flex-wrap items-center gap-2 border-t border-line-200 pt-3">
                        @if ($order->canBePaid() || $order->canPayDifference())
                            <button type="button" wire:click="pay({{ $order->getId() }})" wire:loading.attr="disabled" wire:target="pay({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                                <x-ts-icon icon="credit-card" class="size-5" />
                                {{ $order->canPayDifference() ? 'پرداخت تفاوت مبلغ' : 'پرداخت' }}
                            </button>
                        @else
                            @if ($status !== OrderStatus::Pending)
                                <button type="button" wire:click="reorder({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-primary-700 hover:bg-primary-50">
                                    <x-ts-icon icon="arrow-path" class="size-5" />
                                    سفارش دوباره
                                </button>
                            @endif
                        @endif
                        {{-- A visible way into the order beside the card link (owner, 2026-11-17). --}}
                        <a href="{{ route('account.orders.show', $order->getId()) }}" wire:navigate class="ms-auto inline-flex min-h-11 items-center gap-1 rounded-card border border-line-300 px-3 text-sm font-medium text-ink-900 hover:border-primary-700 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                            جزئیات سفارش
                            <x-ts-icon icon="chevron-left" class="size-4" />
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $orders->links() }}
    @endif
</x-profile::account-shell>
