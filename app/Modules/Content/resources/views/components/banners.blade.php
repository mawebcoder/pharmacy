{{--
    The banners of a place (roadmap D3.6, B9.2, S13b): nothing, one banner, or a Swiper slider of up to 6 (slider in
    resources/js) that swipes with touch, pen and mouse, with a previous and a next arrow on the banner's sides
    (owner, 2026-11-09: no dots below); no autoplay. Each banner shows its desktop, tablet or mobile artwork for the screen.
    <x-content::banners :place="BannerPlace::HomeWidget" :place-id="$widget->getId()" :priority="true" />
--}}
@props([
    'place' => null,
    'placeId' => null,
    'banners' => null,
    'priority' => false,
])

@use('App\Modules\Content\Enums\BannerPlace')
@use('App\Modules\Content\Queries\PlaceBanners')

@php
    $banners ??= $place === null ? collect() : app(PlaceBanners::class)->for($place instanceof BannerPlace ? $place : BannerPlace::from($place), $placeId);
    $count = $banners->count();
@endphp

@if ($count > 0)
    <section
        {{ $attributes->class('relative') }}
        aria-roledescription="carousel"
        aria-label="بنرها"
        @if ($count > 1) x-data="slider({ rewind: true })" @endif
    >
        <div @if ($count > 1) x-ref="slider" @endif class="swiper rounded-card">
            <div class="swiper-wrapper">
                @foreach ($banners as $banner)
                    <a
                        href="{{ $banner->linkUrl() }}"
                        @if ($banner->opensInNewTab()) target="_blank" rel="noopener" @else wire:navigate @endif
                        class="swiper-slide block focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-primary-700"
                        aria-roledescription="slide"
                        aria-label="{{ $banner->getTitle() }} ({{ $loop->iteration }} از {{ $count }})"
                        wire:key="banner-{{ $banner->getId() }}"
                    >
                        <x-media::art-directed
                            :desktop="$banner->getDesktopImage()"
                            desktop-slot="banner-desktop"
                            :tablet="$banner->getTabletImage()"
                            tablet-slot="banner-tablet"
                            :mobile="$banner->getMobileImage()"
                            mobile-slot="banner-mobile"
                            :alt="$banner->getTitle()"
                            :priority="$priority && $loop->first"
                            class="h-auto w-full rounded-card"
                            draggable="false"
                        />
                    </a>
                @endforeach
            </div>
        </div>

        @if ($count > 1)
            <x-content::slider-arrows previous="بنر قبلی" next="بنر بعدی" />
        @endif
    </section>
@endif
