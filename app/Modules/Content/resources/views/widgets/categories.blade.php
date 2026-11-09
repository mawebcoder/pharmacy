{{--
    Categories widget: up to 6 round icons with names. A Swiper slider with side arrows: about 3 on a phone (the next
    one peeks), 5 on a tablet, all 6 on a wide screen.
--}}
@php($categories = $items)

@if ($categories->isNotEmpty())
    <section aria-label="{{ $widget->getTitleFa() ?? 'دسته‌بندی‌ها' }}">
        @include('content::widgets.heading', ['title' => $widget->getTitleFa(), 'href' => route('categories.index')])
        <div
            class="relative"
            x-data="slider({ slidesPerView: 3.3, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 5, spaceBetween: 16 }, 1280: { slidesPerView: 6, spaceBetween: 16 } } })"
        >
            <div x-ref="slider" class="swiper">
                <ul class="swiper-wrapper">
                    @foreach ($categories as $category)
                        <li wire:key="widget-{{ $widget->getId() }}-category-{{ $category->getId() }}" class="swiper-slide w-[30%] md:w-[calc((100%-64px)/5)] xl:w-[calc((100%-80px)/6)]">
                            <a href="{{ route('category.show', $category->getSlug()) }}" wire:navigate class="flex flex-col items-center gap-2 rounded-card p-2 text-center select-none hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                                <span class="grid size-20 place-items-center rounded-full bg-primary-50 xl:size-24">
                                    @if ($category->getIcon())
                                        <x-media::picture :media="$category->getIcon()" slot-name="category-icon" alt="" class="size-12 object-contain xl:size-14" />
                                    @else
                                        <x-ts-icon icon="squares-2x2" class="size-8 text-primary-700" />
                                    @endif
                                </span>
                                <span class="line-clamp-2 text-sm text-ink-900">{{ $category->getNameFa() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-content::slider-arrows previous="دسته‌بندی‌های قبلی" next="دسته‌بندی‌های بعدی" />
        </div>
    </section>
@endif
