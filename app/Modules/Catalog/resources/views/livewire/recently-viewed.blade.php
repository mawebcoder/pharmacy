{{-- «بازدیدهای اخیر» (S13): see App\Modules\Catalog\Livewire\RecentlyViewed. Records this product, then asks for the
     earlier ones when the rail comes near the screen; nothing shows without any. --}}
<div x-data x-init="$store.recentlyViewed.add({{ $productId }})" x-intersect.once.margin.300px="$wire.show($store.recentlyViewed.ids)">
    @if ($products->isNotEmpty())
        <section aria-labelledby="recently-viewed">
            <h2 id="recently-viewed" class="mb-3 text-lg font-bold text-ink-900">بازدیدهای اخیر</h2>
            <div class="relative" wire:ignore x-data="slider({ slidesPerView: 2.15, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 3, spaceBetween: 16 }, 1280: { slidesPerView: 5, spaceBetween: 16 } } })">
                <div x-ref="slider" class="swiper">
                    <ul class="swiper-wrapper">
                        @foreach ($products as $recent)
                            <li wire:key="recent-{{ $recent->getId() }}" class="swiper-slide h-auto! w-[45%] md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-64px)/5)]">
                                <x-catalog::product-card :product="$recent" />
                            </li>
                        @endforeach
                    </ul>
                </div>
                <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
            </div>
        </section>
    @endif
</div>
