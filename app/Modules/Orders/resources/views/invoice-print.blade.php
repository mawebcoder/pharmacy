{{-- «فاکتور فروش» for printing (owner, 2026-11-19): opened in a hidden frame by «چاپ فاکتور», which calls the browser's
     print dialog (as Ctrl+P). See App\Modules\Orders\Queries\OrderInvoice. --}}
@use('App\Modules\Identity\Support\AddressText')
@use('App\Modules\Orders\Support\Code128Svg')
@use('App\Modules\Shared\Support\JalaliDate')
@use('App\Modules\Shared\Support\PersianDigits')
@use('App\Modules\Shared\Support\Toman')
@php
    $address = $order->getAddressSnapshot();
    $customer = $order->getCustomerSnapshot();
    $digits = fn (?string $value): string => PersianDigits::toPersian((string) $value);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فاکتور {{ $order->getNumber() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm 10mm; }
        body { background: #fff; color: #0f172a; font-size: 10.5pt; }
        table { border-collapse: collapse; width: 100%; }
        .cell { border: 1px solid #94a3b8; padding: 6px 8px; vertical-align: top; }
        .head th { background: #0f766e; color: #fff; padding: 6px 8px; border: 1px solid #0f766e; font-weight: 700; }
        .muted { color: #475569; }
        .nowrap { white-space: nowrap; }
        .party { background: #f1f5f9; }
        tr { break-inside: avoid; }
    </style>
</head>
<body>
    <table style="margin-bottom: 10px;">
        <tr>
            <td style="width: 34%;">
                <div style="font-size: 18pt; font-weight: 800; color: #0f766e;">{{ $storeName }}</div>
                <div>فاکتور فروش کالا</div>
            </td>
            <td style="width: 33%; text-align: center;">
                <div>شماره فاکتور: <b dir="ltr">{{ $order->getNumber() }}</b></div>
                <div>تاریخ: {{ JalaliDate::display($order->getOrderDate(), 'Y/m/d') }}</div>
            </td>
            <td style="width: 33%; text-align: left;">
                @if ($order->getTrackingCode())
                    <div style="text-align: right;">کد رهگیری مرسوله</div>
                    {!! Code128Svg::for((string) $order->getTrackingCode(), 44, 1.4) !!}
                    <div dir="ltr" style="font-size: 9pt;">{{ $order->getTrackingCode() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td class="cell party" style="width: 50%;">
                <b>فروشنده:</b> {{ $storeName }}<br>
                @if ($storeAddress) آدرس: {{ $storeAddress }}<br> @endif
                @if ($storePostalCode) کد پستی: {{ $digits($storePostalCode) }}<br> @endif
                @if ($storePhone) تلفن: <span dir="ltr">{{ $digits($storePhone) }}</span> @endif
            </td>
            <td class="cell party" style="width: 50%;">
                <b>خریدار:</b> {{ $address['receiver_name'] ?? $customer['name'] ?? '' }}<br>
                موبایل: <span dir="ltr">{{ $digits($address['receiver_mobile'] ?? $customer['mobile'] ?? '') }}</span><br>
                آدرس: {{ AddressText::from($address)->line() }}<br>
                @if (! empty($address['postal_code'])) کد پستی: {{ $digits($address['postal_code']) }} @endif
            </td>
        </tr>
    </table>

    <table>
        <thead class="head">
            <tr>
                <th style="width: 6%;">ردیف</th>
                <th style="width: 38%;">شرح کالا</th>
                <th style="width: 8%;">تعداد</th>
                <th style="width: 16%;">قیمت واحد</th>
                <th style="width: 14%;">تخفیف</th>
                <th style="width: 18%;">مبلغ کل</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="cell" style="text-align: center;">{{ $digits((string) $loop->iteration) }}</td>
                    <td class="cell">
                        {{ $item->getProductName() }}@if ($item->getVariantTitle()) - {{ $item->getVariantTitle() }}@endif
                        <div class="muted" style="font-size: 8.5pt;" dir="ltr">{{ $item->getSku() }}</div>
                    </td>
                    <td class="cell" style="text-align: center;">{{ $digits((string) $item->getQuantity()) }}</td>
                    <td class="cell nowrap">{{ Toman::format($item->getListPrice()) }}</td>
                    <td class="cell nowrap">{{ Toman::format($item->getHarajDiscountTotal() + $item->getCouponDiscountTotal()) }}</td>
                    <td class="cell nowrap">{{ Toman::format($item->getPaidTotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 40%; margin-top: 10px;">
        <tr><td class="cell">جمع کالاها</td><td class="cell nowrap">{{ Toman::format($order->getSubtotal()) }}</td></tr>
        @if ($order->getDiscountTotal() > 0)
            <tr><td class="cell">تخفیف کد</td><td class="cell nowrap">{{ Toman::format($order->getDiscountTotal()) }}</td></tr>
        @endif
        <tr><td class="cell">هزینه ارسال</td><td class="cell nowrap">{{ $order->getShippingTotal() > 0 ? Toman::format($order->getShippingTotal()) : 'رایگان' }}</td></tr>
        <tr class="party"><td class="cell" style="font-weight: 700;">مبلغ پرداخت‌شده</td><td class="cell nowrap" style="font-weight: 700;">{{ Toman::format($order->getTotal()) }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 10px; font-size: 9pt;">
        @if ($reference) کد پیگیری پرداخت: <span dir="ltr">{{ $digits($reference) }}</span><br> @endif
        روش ارسال: {{ $order->getShippingQuoteSnapshot()['label'] ?? 'تیپاکس' }}
    </p>
</body>
</html>
