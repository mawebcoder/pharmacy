{{--
    «پیشنهاد لحظه‌ای» (roadmap B9.2 section 3, S33): the live flash deals, soonest ending first, with one coral
    countdown to the last deal's end (owner, 2026-11-17) and «مشاهده همه» to /deals. Same slider as a collection rail: about 2 cards on a phone,
    3 on a tablet, 5 on a wide screen. When the countdown reaches zero the page refreshes the widgets.
--}}
@use('App\Modules\Catalog\Queries\FlashDealProducts')

@php
    $endsAt = app(FlashDealProducts::class)->countdownEnd();
    $title = $widget->getTitleFa() ?? 'پیشنهاد لحظه‌ای';
@endphp

@if ($items->isNotEmpty())
    <section aria-label="{{ $title }}">
        <div class="mb-3 flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1">
            <h2 class="flex items-center gap-1.5 text-lg font-bold text-ink-900 xl:text-xl">
                <x-ts-icon icon="bolt" class="size-6 text-accent-600" />
                {{ $title }}
            </h2>
            @if ($endsAt)
                <p class="inline-flex items-center gap-1.5 rounded-full bg-accent-500/10 px-3 text-sm leading-8 text-accent-600" x-data="countdown(@js($endsAt->toIso8601String()), () => $wire.$refresh())">
                    <x-ts-icon icon="clock" class="size-4" />
                    <span class="sr-only">زمان باقی‌مانده:</span>
                    <span class="font-bold tabular-nums" dir="ltr" x-text="remaining"></span>
                </p>
            @endif
            <a href="{{ route('deals') }}" wire:navigate class="ms-auto flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                مشاهده همه تخفیف‌ها
                <x-ts-icon icon="chevron-left" class="size-4" />
            </a>
        </div>
        <div
            class="relative"
            x-data="slider({ slidesPerView: 2.15, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 3, spaceBetween: 16 }, 1280: { slidesPerView: 5, spaceBetween: 16 } } })"
        >
            <div x-ref="slider" class="swiper">
                <ul class="swiper-wrapper">
                    @foreach ($items as $product)
                        <li wire:key="widget-{{ $widget->getId() }}-deal-{{ $product->getId() }}" class="swiper-slide h-auto! w-[45%] md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-64px)/5)]">
                            <x-catalog::product-card :product="$product" />
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-content::slider-arrows previous="پیشنهادهای قبلی" next="پیشنهادهای بعدی" />
        </div>
    </section>
@endif
