{{-- Checkout stepper (roadmap B9.7): «آدرس» then «بازبینی و پرداخت». <x-checkout::stepper current="address" /> --}}
@props(['current'])

@php
    $steps = [
        ['key' => 'address', 'label' => 'آدرس ارسال', 'href' => route('checkout.address')],
        ['key' => 'review', 'label' => 'بازبینی و پرداخت', 'href' => null],
    ];
    $currentIndex = array_search($current, array_column($steps, 'key'), true);
@endphp

<nav aria-label="مراحل خرید">
    <ol class="flex items-center gap-2 text-sm">
        @foreach ($steps as $index => $step)
            <li class="flex items-center gap-2">
                <span @class([
                    'grid size-7 place-items-center rounded-full text-xs font-bold',
                    'bg-primary-700 text-white' => $index <= $currentIndex,
                    'bg-surface-50 text-ink-500 ring-1 ring-line-200' => $index > $currentIndex,
                ])>{{ \App\Modules\Shared\Support\PersianDigits::toPersian($index + 1) }}</span>
                @if ($step['href'] && $index < $currentIndex)
                    <a href="{{ $step['href'] }}" wire:navigate class="text-primary-700 hover:underline">{{ $step['label'] }}</a>
                @else
                    <span @if ($index === $currentIndex) aria-current="step" @endif @class(['font-bold text-ink-900' => $index === $currentIndex, 'text-ink-500' => $index !== $currentIndex])>{{ $step['label'] }}</span>
                @endif
            </li>
            @if (! $loop->last)
                <li aria-hidden="true" class="h-px w-8 bg-line-200"></li>
            @endif
        @endforeach
    </ol>
</nav>
