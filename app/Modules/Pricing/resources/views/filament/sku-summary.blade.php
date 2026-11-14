{{-- Pricing's SKU page header: what the SKU sells at now and where it comes from. --}}
@use('App\Modules\Shared\Support\Toman')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $rows = $summary === null ? [] : [
        ['محصول', $summary->productName],
        ['تنوع', $summary->title.' · '.$summary->sku],
        ['قیمت روی جعبه', $summary->officialPrice ? Toman::format($summary->officialPrice) : '—'],
        ['کمیسیون کلی', $summary->commissionPercent === null ? 'تعیین نشده' : PersianDigits::toPersian(rtrim(rtrim(number_format($summary->commissionPercent, 2), '0'), '.')).'٪ ('.($summary->commissionSource === 'product' ? 'محصول' : 'برند').')'],
        ['قیمت فروش فعلی', $summary->price > 0 ? Toman::format($summary->price) : 'هنوز محاسبه نشده'],
        ['حراج', $summary->salePrice ? Toman::format($summary->salePrice) : '—'],
        ['موجودی', $summary->stockStatus->label()],
        ['تأمین‌کننده فعال', $active === null ? 'ندارد: فروش با قیمت روی جعبه' : ($active->offer->price->getProvider()?->getName() ?? '').' ('.$active->choice->label().')'],
        ['کمیسیون تأمین‌کننده فعال', $active?->offer->commissionPercent === null ? '—' : PersianDigits::toPersian(rtrim(rtrim(number_format($active->offer->commissionPercent, 2), '0'), '.')).'٪ ('.$active->offer->commissionSource?->label().')'],
    ];
@endphp

<dl style="display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 0.75rem 1.5rem;">
    @foreach ($rows as [$label, $value])
        <div>
            <dt style="font-size: 0.75rem; opacity: 0.7;">{{ $label }}</dt>
            <dd style="font-weight: 600;">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
