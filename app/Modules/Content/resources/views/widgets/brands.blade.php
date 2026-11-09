{{--
    Brands widget: up to 6 round logos, grey until hovered on desktop (roadmap B9.2 item 7). A Swiper slider with side
    arrows: about 3 logos on a phone (the next one peeks), 5 on a tablet, all 6 on a wide screen.
--}}
@php($brands = $items)

@if ($brands->isNotEmpty())
    <section aria-label="{{ $widget->getTitleFa() ?? 'برندها' }}">
        @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => route('brands.index')])
        <div
            class="relative"
            x-data="slider({ slidesPerView: 3.3, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 5, spaceBetween: 16 }, 1280: { slidesPerView: 6, spaceBetween: 16 } } })"
        >
            <div x-ref="slider" class="swiper">
                <ul class="swiper-wrapper">
                    @foreach ($brands as $brand)
                        <li wire:key="widget-{{ $widget->getId() }}-brand-{{ $brand->getId() }}" class="swiper-slide w-[30%] md:w-[calc((100%-64px)/5)] xl:w-[calc((100%-80px)/6)]">
                            <a href="{{ route('brands.show', $brand->getSlug()) }}" wire:navigate class="group flex flex-col items-center gap-2 rounded-card p-2 text-center select-none focus-visible:outline-2 focus-visible:outline-primary-700">
                                <span class="grid size-20 place-items-center overflow-hidden rounded-full border border-line-200 bg-white p-3 xl:size-24">
                                    @if ($brand->getLogo())
                                        <x-media::picture :media="$brand->getLogo()" slot-name="brand-logo" :alt="$brand->getNameFa()" class="size-full object-contain transition xl:grayscale xl:group-hover:grayscale-0 motion-reduce:transition-none" />
                                    @else
                                        <span class="text-sm font-bold text-ink-500">{{ mb_substr($brand->getNameFa(), 0, 2) }}</span>
                                    @endif
                                </span>
                                <span class="line-clamp-1 text-sm text-ink-900">{{ $brand->getNameFa() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-content::slider-arrows previous="برندهای قبلی" next="برندهای بعدی" />
        </div>
    </section>
@endif
