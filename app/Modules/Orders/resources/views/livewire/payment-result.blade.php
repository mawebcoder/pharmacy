{{-- After the gateway (roadmap B9.8): see App\Modules\Orders\Livewire\PaymentResult. --}}
@use('App\Modules\Orders\Enums\OrderStatus')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')

@php($status = $order->getStatus())

<div class="mx-auto max-w-lg">
    <div class="rounded-card border border-line-200 bg-card p-6 text-center md:p-8">
        @if ($status->isPurchase() || $status === OrderStatus::Cancelled)
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-success-600/10 text-success-600">
                <x-ts-icon icon="check-circle" class="size-9" />
            </span>
            <h1 class="mt-4 text-xl font-bold text-ink-900">پرداخت انجام شد</h1>
            <p class="mt-2 text-sm text-ink-500">سفارش شما ثبت شد و به‌زودی آماده‌سازی می‌شود.</p>
        @elseif ($order->canBePaid())
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-warning-500/15 text-ink-900">
                <x-ts-icon icon="exclamation-triangle" class="size-9 text-warning-500" />
            </span>
            <h1 class="mt-4 text-xl font-bold text-ink-900">پرداخت انجام نشد</h1>
            <p class="mt-2 text-sm leading-7 text-ink-500">{{ $failureReason ?? 'پرداخت تکمیل نشد.' }} سفارش تا {{ JalaliDate::display($order->getExpiresAt(), 'H:i') }} برای شما نگه داشته می‌شود.</p>
        @else
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-surface-50 text-ink-500 ring-1 ring-line-200">
                <x-ts-icon icon="clock" class="size-9" />
            </span>
            <h1 class="mt-4 text-xl font-bold text-ink-900">مهلت پرداخت تمام شد</h1>
            <p class="mt-2 text-sm text-ink-500">این سفارش پرداخت نشد. می‌توانید کالاهای آن را دوباره به سبد بیاورید.</p>
        @endif

        <div class="mt-5 rounded-card bg-surface-50 p-4 text-sm" x-data="{ copied: false }">
            <p class="text-ink-500">شماره سفارش</p>
            <div class="mt-1 flex items-center justify-center gap-2">
                <span class="text-lg font-bold text-ink-900 select-all" dir="ltr">{{ $order->getNumber() }}</span>
                <button type="button" x-on:click="$copy(@js($order->getNumber())).then((ok) => { copied = ok; setTimeout(() => (copied = false), 2000) })" class="grid size-11 place-items-center rounded-full text-primary-700 hover:bg-primary-50" aria-label="کپی شماره سفارش">
                    <x-ts-icon icon="clipboard-document" class="size-5" x-show="! copied" />
                    <x-ts-icon icon="check" class="size-5" x-show="copied" x-cloak />
                </button>
            </div>
            <p class="mt-2 text-ink-900">مبلغ: @toman($order->getTotal())</p>
            @if ($reference)
                <p class="mt-1 text-ink-500">کد پیگیری پرداخت: <span dir="ltr">{{ PersianDigits::toPersian($reference) }}</span></p>
            @endif
        </div>

        <div class="mt-5 grid gap-2">
            @if ($order->canBePaid())
                <button type="button" wire:click="pay({{ $order->getId() }})" wire:loading.attr="disabled" class="flex h-12 items-center justify-center rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800">تلاش دوباره</button>
            @elseif ($status === OrderStatus::Expired)
                <button type="button" wire:click="reorder({{ $order->getId() }})" class="flex h-12 items-center justify-center rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800">سفارش دوباره</button>
            @endif
            <a href="{{ route('account.orders') }}" wire:navigate class="flex h-12 items-center justify-center rounded-card border border-line-200 text-ink-900 hover:bg-surface-50">پیگیری سفارش</a>
        </div>
    </div>
</div>
