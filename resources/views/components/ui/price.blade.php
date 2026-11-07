{{--
    Price block (roadmap B9.4, B10). Prices are rial; shown in toman with Persian digits.
    <x-ui.price :price="$variant->price" :old="$variant->official_price" />
    - discounted: old price struck through with a discount badge, then the new price
    - free (0): "رایگان" in the success colour
--}}
@props([
    'price',
    'old' => null,
    'size' => 'md',
])

@use('App\Modules\Shared\Support\PersianDigits')
@use('App\Modules\Shared\Support\Toman')

@php
    $price = (int) $price;
    $discount = $old !== null ? Toman::discountPercent((int) $old, $price) : 0;
    $priceSize = match ($size) {
        'sm' => 'text-sm',
        'lg' => 'text-xl',
        default => 'text-base',
    };
@endphp

<div {{ $attributes->class('flex flex-col items-start') }}>
    @if ($discount > 0)
        <div class="flex flex-wrap items-center gap-x-2">
            <del class="text-sm whitespace-nowrap text-ink-500">
                <span class="sr-only">قیمت قبلی:</span>
                @toman($old)
            </del>
            <span class="rounded-full bg-accent-500 px-2 text-xs leading-5 font-bold text-white">
                {{ PersianDigits::toPersian($discount) }}٪
                <span class="sr-only">تخفیف</span>
            </span>
        </div>
    @endif

    @if ($price === 0)
        <span @class(['font-bold text-success-600', $priceSize])>رایگان</span>
    @else
        <span @class(['font-bold whitespace-nowrap text-ink-900', $priceSize])>@toman($price)</span>
    @endif
</div>
