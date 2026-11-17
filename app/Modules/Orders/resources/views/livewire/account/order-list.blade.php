{{-- «سفارش‌ها» (roadmap B9.9): see App\Modules\Orders\Livewire\Account\OrderList. --}}
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

<x-identity::account-shell active="orders" title="سفارش‌ها">
    @if ($orders->isEmpty())
        <x-identity::empty-state icon="shopping-bag" message="هنوز سفارشی ثبت نکرده‌اید." hint="سفارش‌های شما و وضعیت ارسالشان اینجا نمایش داده می‌شود." />
    @else
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

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="flex -space-x-2" aria-label="{{ PersianDigits::toPersian($order->items->count()) }} کالا">
                            @foreach ($order->items->take(4) as $item)
                                <span class="size-11 overflow-hidden rounded-full border-2 border-card bg-media ring-1 ring-line-200">
                                    @if ($item->getImageUrl())
                                        <img src="{{ $item->getImageUrl() }}" alt="" width="44" height="44" class="size-full object-contain" loading="lazy">
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
                        @if ($order->canBePaid())
                            <button type="button" wire:click="pay({{ $order->getId() }})" wire:loading.attr="disabled" wire:target="pay({{ $order->getId() }})" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                                <x-ts-icon icon="credit-card" class="size-5" />
                                پرداخت
                            </button>
                            <span class="text-xs text-ink-500">مهلت پرداخت تا {{ JalaliDate::display($order->getExpiresAt(), 'H:i') }}</span>
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
</x-identity::account-shell>
