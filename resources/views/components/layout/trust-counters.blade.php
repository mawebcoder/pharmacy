{{--
    Trust counters (roadmap B7 Daroodrug, S35): products for sale, delivered orders and cities delivered to, counted
    live (cached an hour) by Settings' TrustCounters; a counter still too small is left out, and the row with it.
    The footer leaves out the products for sale (owner, 2026-11-17): <x-layout.trust-counters :products="false" />
--}}
@props(['products' => true])

@php
    $counters = app(\App\Modules\Settings\Queries\TrustCounters::class)->shown($products);
@endphp

@if ($counters !== [])
    <ul {{ $attributes->class('flex flex-wrap justify-center gap-x-10 gap-y-4') }} aria-label="آمار فروشگاه">
        @foreach ($counters as $counter)
            <li class="flex items-center gap-3">
                <x-ts-icon :icon="$counter['icon']" class="size-7 text-primary-700" />
                <span>
                    <span class="block text-lg font-bold text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian(number_format($counter['value'])) }}</span>
                    <span class="block text-xs text-ink-500">{{ $counter['label'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
