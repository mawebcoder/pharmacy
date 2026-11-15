{{--
    Collection rail: the first 6 in-stock products of a collection in its order, with «مشاهده همه». A Swiper slider
    with side arrows: about 2 cards on a phone (the next one peeks), 3 on a tablet, all 6 on a wide screen.
--}}
@use('App\Modules\Catalog\Queries\HomeItems')

@php
    $collection = $widget->getCollectionId() === null ? null : app(HomeItems::class)->collection($widget->getCollectionId());
    $products = $items;
@endphp

@if ($collection !== null && $products->isNotEmpty())
    <section aria-label="{{ $widget->getTitleFa() ?? $collection->getNameFa() }}">
        @include('content::widgets.heading', ['title' => $widget->getTitleFa() ?? $collection->getNameFa(), 'href' => route('collections.show', $collection)])
        <div
            class="relative"
            x-data="slider({ slidesPerView: 2.15, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 3, spaceBetween: 16 }, 1280: { slidesPerView: 5, spaceBetween: 16 } } })"
        >
            <div x-ref="slider" class="swiper">
                <ul class="swiper-wrapper">
                    @foreach ($products as $product)
                        <li wire:key="widget-{{ $widget->getId() }}-product-{{ $product->getId() }}" class="swiper-slide h-auto! w-[45%] md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-64px)/5)]">
                            <x-catalog::product-card :product="$product" />
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
        </div>
    </section>
@endif
