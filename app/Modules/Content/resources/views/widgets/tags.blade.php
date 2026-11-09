{{--
    Tags widget: up to 6 need/nutrient chips (roadmap B9.2 item 2). A Swiper slider with side arrows when the chips do
    not fit on one line; otherwise they sit still in a row.
--}}
@php($tags = $items)

@if ($tags->isNotEmpty())
    <section aria-label="{{ $widget->getTitleFa() ?? 'خرید بر اساس نیاز' }}">
        @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => null])
        <div class="relative" x-data="slider({ slidesPerView: 'auto', spaceBetween: 8 })">
            <nav x-ref="slider" class="swiper" aria-label="{{ $widget->getTitleFa() ?? 'خرید بر اساس نیاز' }}">
                <ul class="swiper-wrapper">
                    @foreach ($tags as $tag)
                        <li wire:key="widget-{{ $widget->getId() }}-tag-{{ $tag->getId() }}" class="swiper-slide w-auto!">
                            <x-catalog::tag-chip :tag="$tag" class="select-none" />
                        </li>
                    @endforeach
                </ul>
            </nav>
            <x-content::slider-arrows previous="برچسب‌های قبلی" next="برچسب‌های بعدی" />
        </div>
    </section>
@endif
