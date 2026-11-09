{{--
    Trust block (roadmap B9.2 item 8): licences, pharmacist, delivery, returns. Used in the footer (a grid) and as the
    home page's «اعتماد» widget (:slider="true": a Swiper slider with side arrows when the four items do not fit).
    <x-layout.trust class="…" />  ·  <x-layout.trust :slider="true" />
--}}
@props([
    'slider' => false,
])

@php
    $items = [
        ['icon' => 'shield-check', 'title' => 'اصالت کالا', 'text' => 'خرید مستقیم از شرکت‌های پخش معتبر'],
        ['icon' => 'user-circle', 'title' => 'زیر نظر داروساز', 'text' => 'پاسخ‌گویی داروساز مسئول'],
        ['icon' => 'truck', 'title' => 'ارسال به سراسر کشور', 'text' => 'تیپاکس و پیک اختصاصی'],
        ['icon' => 'arrow-path', 'title' => 'ضمانت بازگشت', 'text' => 'هفت روز ضمانت بازگشت کالا'],
    ];
@endphp

@if ($slider)
    <div
        {{ $attributes->class('relative') }}
        x-data="slider({ slidesPerView: 1.6, spaceBetween: 16, breakpoints: { 768: { slidesPerView: 3, spaceBetween: 16 }, 1280: { slidesPerView: 4, spaceBetween: 16 } } })"
    >
        <div x-ref="slider" class="swiper">
            <ul class="swiper-wrapper">
                @foreach ($items as $item)
                    <li class="swiper-slide w-[60%] select-none md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-48px)/4)]">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-700">
                                <x-ts-icon :icon="$item['icon']" class="size-6" />
                            </span>
                            <span>
                                <span class="block text-sm font-bold text-ink-900">{{ $item['title'] }}</span>
                                <span class="block text-xs text-ink-500">{{ $item['text'] }}</span>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <x-content::slider-arrows previous="مورد قبلی" next="مورد بعدی" />
    </div>
@else
    <ul {{ $attributes->class('grid grid-cols-2 gap-4 xl:grid-cols-4') }}>
        @foreach ($items as $item)
            <li class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-700">
                    <x-ts-icon :icon="$item['icon']" class="size-6" />
                </span>
                <span>
                    <span class="block text-sm font-bold text-ink-900">{{ $item['title'] }}</span>
                    <span class="block text-xs text-ink-500">{{ $item['text'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
