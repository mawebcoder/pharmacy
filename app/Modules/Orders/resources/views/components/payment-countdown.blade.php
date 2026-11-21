{{--
    An unpaid order's payment deadline, or the difference owed after staff edited a paid order (owner, 2026-11-19): a
    warning with the time left, counting down; when it reaches zero the page refreshes. On the order list (compact) and the order page.
--}}
@props(['order', 'compact' => false])
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\Toman')

@php($difference = $order->canPayDifference())
@php($deadline = $difference ? $order->getDifferenceDueUntil() : ($order->canBePaid() ? $order->getExpiresAt() : null))
@if ($deadline)
    <div
        role="alert"
        x-data="countdown(@js($deadline->toIso8601String()), () => $wire.$refresh())"
        @class(['flex flex-wrap items-center gap-x-2 gap-y-1 rounded-card border border-warning-500/40 bg-warning-500/10 text-ink-900', 'p-3 text-sm' => ! $compact, 'px-3 py-2 text-xs' => $compact])
        data-test="payment-countdown"
    >
        <x-ts-icon icon="clock" @class(['shrink-0', 'size-5' => ! $compact, 'size-4' => $compact]) />
        <span>
            @if ($difference)
                سفارش شما ویرایش شد و {{ Toman::format($order->getDifferenceDue()) }} باید پرداخت کنید؛
            @else
                این سفارش هنوز پرداخت نشده است؛
            @endif
            <span class="font-bold tabular-nums" dir="ltr" x-text="remaining"></span>
            تا پایان مهلت پرداخت
            @unless ($compact)
                (تا {{ JalaliDate::display($deadline, 'j F، ساعت H:i') }}).
                {{ $difference ? 'سفارش تا پرداخت این مبلغ ارسال نمی‌شود.' : 'پس از آن سفارش لغو می‌شود و باید دوباره ثبت شود.' }}
            @endunless
        </span>
    </div>
@endif
